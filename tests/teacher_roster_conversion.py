#!/usr/bin/env python3
"""Verify the release CSV against the supplied workbook and converter edge cases."""
import csv
import importlib.util
from pathlib import Path
import sys
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[1]
sys.dont_write_bytecode = True
spec = importlib.util.spec_from_file_location('roster_converter', ROOT / 'bin/convert_teacher_roster.py')
converter = importlib.util.module_from_spec(spec)
spec.loader.exec_module(converter)
rows = converter.read_roster(ROOT / 'docs/pedagogu_saraksts.xlsx')
with (ROOT / 'database/imports/pedagogu_saraksts_2026.csv').open(encoding='utf-8', newline='') as source:
    reader = csv.reader(source)
    assert next(reader) == ['name', 'surname', 'cabinet']
    assert list(reader) == rows
assert len(rows) == 103
assert sum(name == 'Tatjana' and surname == 'Aļeņina' for name, surname, _ in rows) == 1
assert ['Inese', 'Jeļkina', '248.'] in rows
assert ['Natālija', 'N.Kožuhova', 'skolotāju istaba'] in rows
print('PASS XLSX matches release CSV: 103 teachers, approved duplicate merged, Latvian text and rooms preserved')

with tempfile.TemporaryDirectory(prefix='scheduler-converter-') as directory:
    original = ROOT / 'docs/pedagogu_saraksts.xlsx'
    for mode in ['empty-cell', 'formula', 'conflicting-room']:
        path = Path(directory) / (mode + '.xlsx')
        with zipfile.ZipFile(original) as source, zipfile.ZipFile(path, 'w') as target:
            for item in source.infolist():
                data = source.read(item.filename)
                if item.filename == 'xl/worksheets/sheet1.xml':
                    root = converter.ET.fromstring(data)
                    cell = root.find('.//s:c[@r="A2"]', converter.NS)
                    if mode == 'empty-cell':
                        for child in list(cell):
                            cell.remove(child)
                        cell.attrib.pop('t', None)
                    elif mode == 'formula':
                        converter.ET.SubElement(cell, '{' + converter.NS['s'] + '}f').text = '1+1'
                    else:
                        cell = root.find('.//s:c[@r="C101"]', converter.NS)
                        cell.attrib.pop('t', None)
                        cell.find('s:v', converter.NS).text = 'different room'
                    data = converter.ET.tostring(root)
                target.writestr(item, data)
        try:
            converter.read_roster(path)
            raise AssertionError('Invalid workbook accepted: ' + mode)
        except ValueError:
            pass
print('PASS incomplete rows, formulas and conflicting duplicates are rejected')
