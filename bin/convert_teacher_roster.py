#!/usr/bin/env python3
"""Convert the three roster columns from XLSX to UTF-8 CSV (standard library only)."""
import argparse
import csv
import posixpath
import sys
import unicodedata
import xml.etree.ElementTree as ET
import zipfile

NS = {'s': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}


def normalized(value):
    return unicodedata.normalize('NFC', value.strip())


def read_roster(path):
    with zipfile.ZipFile(path) as archive:
        workbook = ET.fromstring(archive.read('xl/workbook.xml'))
        sheets = workbook.findall('s:sheets/s:sheet', NS)
        if len(sheets) != 1:
            raise ValueError('Expected exactly one worksheet.')
        relationship_id = sheets[0].get('{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id')
        relationships = ET.fromstring(archive.read('xl/_rels/workbook.xml.rels'))
        target = next(item.get('Target') for item in relationships if item.get('Id') == relationship_id)
        worksheet = posixpath.normpath('xl/' + target) if not target.startswith('/') else target.lstrip('/')
        strings = []
        if 'xl/sharedStrings.xml' in archive.namelist():
            strings = [''.join(node.text or '' for node in item.iterfind('.//s:t', NS))
                       for item in ET.fromstring(archive.read('xl/sharedStrings.xml')).findall('s:si', NS)]
        records, seen = [], {}
        for row in ET.fromstring(archive.read(worksheet)).findall('s:sheetData/s:row', NS):
            cells = {}
            for cell in row.findall('s:c', NS):
                column = ''.join(char for char in cell.get('r', '') if char.isalpha())
                if column not in ('A', 'B', 'C'):
                    continue
                if cell.find('s:f', NS) is not None:
                    raise ValueError('Formulas are not supported in roster columns.')
                value = cell.find('s:v', NS)
                text = value.text or '' if value is not None else ''.join(node.text or '' for node in cell.iterfind('.//s:t', NS))
                if cell.get('t') == 's':
                    text = strings[int(text)]
                cells[column] = normalized(text)
            values = [cells.get(column, '') for column in ('A', 'B', 'C')]
            if row.get('r') == '1':
                if values != ['Vārds', 'Uzvārds', 'Telpa']:
                    raise ValueError('Expected headers: Vārds, Uzvārds, Telpa.')
                continue
            if not any(values):
                continue
            if not all(values):
                raise ValueError(f'Incomplete teacher in row {row.get("r")}.')
            key = tuple(value.casefold() for value in values[:2])
            if key in seen:
                if seen[key][2].casefold() != values[2].casefold():
                    raise ValueError(f'Duplicate name with different rooms in row {row.get("r")}.')
                print(f'Merged duplicate teacher at worksheet row {row.get("r")}.', file=sys.stderr)
                continue
            records.append(values)
            seen[key] = values
        if not records:
            raise ValueError('The worksheet contains no teachers.')
        return records


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('xlsx')
    parser.add_argument('csv')
    args = parser.parse_args()
    records = read_roster(args.xlsx)
    with open(args.csv, 'w', newline='', encoding='utf-8') as output:
        writer = csv.writer(output, lineterminator='\n')
        writer.writerow(['name', 'surname', 'cabinet'])
        writer.writerows(records)
    print(f'Converted {len(records)} teachers.', file=sys.stderr)


if __name__ == '__main__':
    main()
