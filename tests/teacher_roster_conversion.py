#!/usr/bin/env python3
"""Test XLSX conversion with invented records; no real roster is required."""
import csv
import importlib.util
from pathlib import Path
import sys
import tempfile
import zipfile
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
sys.dont_write_bytecode = True
spec = importlib.util.spec_from_file_location('roster_converter', ROOT / 'bin/convert_teacher_roster.py')
converter = importlib.util.module_from_spec(spec)
spec.loader.exec_module(converter)
with (ROOT / 'database/fixtures/teacher_roster.csv').open(encoding='utf-8', newline='') as source:
    reader = csv.reader(source)
    assert next(reader) == ['name', 'surname', 'cabinet']
    rows = list(reader)
assert len(rows) == 103
NS = converter.NS['s']


def write_workbook(path, mode='valid'):
    sheet = ET.Element('{' + NS + '}worksheet')
    data = ET.SubElement(sheet, '{' + NS + '}sheetData')
    values = [['Vārds', 'Uzvārds', 'Telpa']] + rows + [[*rows[0][:2], rows[0][2].lower()]]
    for number, record in enumerate(values, 1):
        row = ET.SubElement(data, '{' + NS + '}row', {'r': str(number)})
        for column, text in zip('ABC', record):
            cell = ET.SubElement(row, '{' + NS + '}c', {'r': column + str(number), 't': 'inlineStr'})
            inline = ET.SubElement(cell, '{' + NS + '}is')
            ET.SubElement(inline, '{' + NS + '}t').text = ('  ' + text + ' ') if number == 2 else text
            if number == 2 and column == 'A':
                if mode == 'empty-cell':
                    inline.clear()
                elif mode == 'formula':
                    ET.SubElement(cell, '{' + NS + '}f').text = '1+1'
            if number == len(values) and column == 'C' and mode == 'conflicting-room':
                inline.find('{' + NS + '}t').text = 'OTHER-ROOM'
        if number == 3:
            cell = ET.SubElement(row, '{' + NS + '}c', {'r': 'AL3', 't': 'inlineStr'})
            ET.SubElement(ET.SubElement(cell, '{' + NS + '}is'), '{' + NS + '}t').text = 'ignored outside the roster'
    with zipfile.ZipFile(path, 'w') as archive:
        archive.writestr('xl/workbook.xml', '<workbook xmlns="' + NS + '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Test" sheetId="1" r:id="rId1"/></sheets></workbook>')
        archive.writestr('xl/_rels/workbook.xml.rels', '<Relationships><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>')
        archive.writestr('xl/worksheets/sheet1.xml', ET.tostring(sheet))


with tempfile.TemporaryDirectory(prefix='scheduler-converter-') as directory:
    path = Path(directory) / 'test.xlsx'
    write_workbook(path)
    assert converter.read_roster(path) == rows
    print('PASS invented XLSX: 103 teachers, whitespace trimmed, duplicate merged, Latvian text and rooms preserved')
    for mode in ['empty-cell', 'formula', 'conflicting-room']:
        write_workbook(path, mode)
        try:
            converter.read_roster(path)
            raise AssertionError('Invalid workbook accepted: ' + mode)
        except ValueError:
            pass
print('PASS incomplete rows, formulas and conflicting duplicates are rejected')
