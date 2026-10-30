#!/usr/bin/env python3
"""Prepare and verify a separate cleaned Git mirror. Never changes source refs or pushes."""
import argparse
import csv
import json
import os
from pathlib import Path
import re
import shutil
import shlex
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
PRIVATE_PATH = re.compile(r'(?i)^(?:docs/.*\.(?:sql|csv|xlsx|xls)|database/imports/.*\.csv)$')


def git(repo, *args):
    return subprocess.check_output(['git', '-C', str(repo), *args])


def blobs(repo):
    objects = git(repo, 'rev-list', '--objects', '--all').splitlines()
    entries = [(line.split(b' ', 1)[0], line.split(b' ', 1)[1].decode('utf-8'))
               for line in objects if b' ' in line]
    process = subprocess.Popen(['git', '-C', str(repo), 'cat-file', '--batch'], stdin=subprocess.PIPE, stdout=subprocess.PIPE)
    try:
        for oid, path in entries:
            if path.startswith(('vendor/', 'pdflib-', 'fpdf')):
                continue
            process.stdin.write(oid + b'\n')
            process.stdin.flush()
            header = process.stdout.readline().split()
            data = process.stdout.read(int(header[2]))
            process.stdout.read(1)
            if header[1] == b'blob':
                yield path, data
    finally:
        process.stdin.close()
        process.stdout.close()
        process.wait()


def sql_names(text):
    create = re.search(r'CREATE TABLE(?: IF NOT EXISTS)?\s+`?teacher`?\s*\((.*?)\)\s*(?:ENGINE|;)', text, re.I | re.S)
    defaults = re.findall(r'^\s*`([^`]+)`\s+', create.group(1), re.M) if create else []
    for match in re.finditer(r'INSERT INTO\s+`?teacher`?\s*(?:\(([^)]*)\))?\s*VALUES\s*(.*?);', text, re.I | re.S):
        columns = [part.strip().strip('`') for part in match.group(1).split(',')] if match.group(1) else defaults
        for row in re.finditer(r"\(((?:[^()']|'(?:[^'\\]|\\.|'')*')*)\)", match.group(2)):
            values = re.findall(r"'(?:[^'\\]|\\.|'')*'|[^,\s]+", row.group(1))
            for index, column in enumerate(columns):
                if column in ('name', 'surname') and index < len(values) and values[index].startswith("'"):
                    yield values[index][1:-1].replace("\\'", "'").replace("''", "'")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--source', type=Path, default=ROOT)
    parser.add_argument('--filter-repo', default='git-filter-repo')
    parser.add_argument('--roster', type=Path, default=ROOT / 'database/imports/pedagogu_saraksts_2026.csv')
    args = parser.parse_args()
    source = args.source.resolve()
    if git(source, 'branch', '--show-current').strip() != b'main':
        raise SystemExit('Prepare cleanup from main after committing the reviewed changes there locally; do not push first.')
    tool = shutil.which(args.filter_repo)
    if tool is None:
        raise SystemExit('Install git-filter-repo >= 2.47 in a separate environment and pass --filter-repo /path/to/git-filter-repo.')
    names = set()
    if args.roster.is_file():
        with args.roster.open(encoding='utf-8', newline='') as stream:
            for row in csv.DictReader(stream):
                names.update(value for key, value in row.items() if key in ('name', 'surname') and value)
    for path, data in blobs(source):
        if path.lower().endswith('.sql') and PRIVATE_PATH.fullmatch(path):
            names.update(sql_names(data.decode('utf-8', errors='replace')))
    tokens = {token for name in names for token in re.findall(r'[^\W\d_]+(?:[.-][^\W\d_]+)*', name) if len(token) >= 3}
    if not tokens:
        raise SystemExit('No roster names found. Restore a private CSV or SQL export before preparing the cleanup.')
    pattern = r'(?<!\w)(?:' + '|'.join(re.escape(token) for token in sorted(tokens, key=len, reverse=True)) + r')(?!\w)'
    matcher = re.compile(pattern, re.I)
    folder = ROOT / 'var'
    folder.mkdir(exist_ok=True)
    output = Path(tempfile.mkdtemp(prefix='history-cleanup-', dir=folder))
    output.chmod(0o700)
    backup = output / 'before-cleanup.bundle'
    subprocess.run(['git', '-C', str(source), 'bundle', 'create', str(backup), '--all'], check=True, stdout=subprocess.DEVNULL)
    backup.chmod(0o600)
    mirror = output / 'clean.git'
    subprocess.run(['git', 'clone', '--mirror', '--no-local', str(source), str(mirror)], check=True)
    if mirror.resolve() == source or git(mirror, 'rev-parse', '--is-bare-repository').strip() != b'true':
        raise SystemExit('Refusing to rewrite anything except the newly created bare mirror.')
    # Local editor snapshots can point directly at trees and are not exported as commits.
    for line in git(mirror, 'for-each-ref', '--format=%(refname) %(objecttype)').decode().splitlines():
        ref, kind = line.split()
        if kind not in ('commit', 'tag'):
            subprocess.run(['git', '-C', str(mirror), 'update-ref', '-d', ref], check=True)
    original_refs = {ref: oid for oid, ref in (line.split() for line in git(source, 'show-ref').decode().splitlines())}
    # Keep remote branch tips, using local main only when it contains the remote main.
    remote_branches = {}
    for ref, oid in original_refs.items():
        if ref.startswith('refs/remotes/origin/') and not ref.endswith('/HEAD'):
            name = ref.removeprefix('refs/remotes/origin/')
            remote_branches[name] = oid
            if name == 'main':
                subprocess.run(['git', '-C', str(source), 'merge-base', '--is-ancestor', oid, 'main'], check=True)
            else:
                subprocess.run(['git', '-C', str(mirror), 'update-ref', 'refs/heads/' + name, oid], check=True)
    patterns = output / 'private-pattern.json'
    patterns.write_text(json.dumps(pattern), encoding='utf-8')
    patterns.chmod(0o600)
    callback = output / 'file-callback.py'
    callback.write_text('''
if filename.startswith((b'vendor/', b'pdflib-', b'fpdf')) or mode == b'160000':
    return (filename, mode, blob_id)
if 'teacher_pattern' not in value.data:
    import json, re
    value.data['teacher_pattern'] = re.compile(json.load(open(PATTERN_FILE, encoding='utf-8')), re.I)
contents = value.get_contents_by_identifier(blob_id)
if value.is_binary(contents):
    return (filename, mode, blob_id)
try:
    text = contents.decode('utf-8')
except UnicodeDecodeError:
    return (filename, mode, blob_id)
cleaned = value.data['teacher_pattern'].sub('REDACTED_TEACHER', text).encode('utf-8')
if cleaned != contents:
    blob_id = value.insert_file_with_contents(cleaned)
return (filename, mode, blob_id)
'''.replace('PATTERN_FILE', repr(str(patterns))), encoding='utf-8')
    message_callback = 'import json, re\np = re.compile(json.load(open(' + repr(str(patterns)) + ", encoding='utf-8')), re.I)\nreturn p.sub('REDACTED_TEACHER', message.decode('utf-8')).encode('utf-8')"
    # A mirror can include refs/stash. Force is limited to this new disposable mirror.
    subprocess.run([tool, '--force', '--sensitive-data-removal', '--no-fetch', '--invert-paths', '--path-regex', PRIVATE_PATH.pattern,
                    '--file-info-callback', str(callback), '--message-callback', message_callback], cwd=mirror, check=True)
    leaks = []
    checked = 0
    for path, data in blobs(mirror):
        checked += 1
        if PRIVATE_PATH.fullmatch(path):
            leaks.append(path)
            continue
        try:
            if matcher.search(data.decode('utf-8')):
                leaks.append(path)
        except UnicodeDecodeError:
            pass
    messages = git(mirror, 'log', '--all', '--format=%B').decode('utf-8')
    if matcher.search(messages):
        leaks.append('commit messages')
    if leaks:
        raise SystemExit('Cleanup verification failed in: ' + ', '.join(sorted(set(leaks))))
    dirty = bool(git(source, 'status', '--porcelain'))
    refs = git(mirror, 'for-each-ref', '--format=%(refname) %(objectname)').decode().splitlines()
    report = {
        'source_head': git(source, 'rev-parse', 'HEAD').decode().strip(),
        'source_has_uncommitted_changes': dirty,
        'mirror': str(mirror), 'backup': str(backup),
        'checked_project_blobs': checked, 'known_name_tokens': len(tokens),
        'remaining_known_names_or_private_exports': 0,
        'refs': refs,
        'note': 'No source refs changed and nothing pushed. Commit pending source changes, then rerun this script before publishing.' if dirty else 'Nothing pushed. Review and publish using docs/PRIVATE-DATA.md.',
    }
    (output / 'report.json').write_text(json.dumps(report, indent=2) + '\n')
    (output / 'source-refs.txt').write_bytes(git(source, 'show-ref'))
    push = ['git', '-C', str(mirror), 'push', '--atomic']
    for branch, oid in sorted(remote_branches.items()):
        push.append('--force-with-lease=refs/heads/' + branch + ':' + oid)
    tags = git(source, 'for-each-ref', '--format=%(refname) %(objectname)', 'refs/tags/').decode().splitlines()
    for tag in tags:
        ref, oid = tag.split()
        push.append('--force-with-lease=' + ref + ':' + oid)
    push.append(git(source, 'remote', 'get-url', 'origin').decode().strip())
    push.extend('refs/heads/' + branch + ':refs/heads/' + branch for branch in sorted(remote_branches))
    push.extend(line.split()[0] + ':' + line.split()[0] for line in tags)
    publish = '#!/bin/bash\nset -euo pipefail\n'
    if dirty:
        publish += 'printf "%s\\n" "Preview only: commit the source changes and prepare a new mirror first." >&2\nexit 1\n'
    else:
        publish += '# Manual publication only. Disable Actions while rewriting all historical branches.\n' + shlex.join(push) + '\n'
    (output / 'publish-reviewed-history.sh').write_text(publish)
    print('Prepared cleaned history:', mirror)
    print('Verified project blobs:', checked)
    print('Remaining known teacher names and private exports: 0')
    print('Original history backup:', backup)
    if dirty:
        print('PREVIEW ONLY: source has uncommitted changes. Commit them and rerun before publishing.')
    print('Report:', output / 'report.json')


if __name__ == '__main__':
    main()
