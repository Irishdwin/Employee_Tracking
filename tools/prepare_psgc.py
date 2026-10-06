"""Offline import preparation: official PSA workbook -> validated local JSON.
Usage: python tools/prepare_psgc.py workbook.xlsx output.json
Requires openpyxl for import preparation only; never invoked by the website.
"""
import sys, json, hashlib, re
from collections import Counter
from pathlib import Path
import openpyxl

def prepare(path):
    book = openpyxl.load_workbook(path, read_only=True, data_only=True)
    publication = next(row[1] for row in book['Metadata'].iter_rows(values_only=True) if row[0] == 'Publication date:')
    assert publication == '30 June 2026', 'Unexpected publication; review counts/version before importing'
    publication = next(row[1] for row in book['Metadata'].iter_rows(values_only=True) if row[0] == 'Publication date:')
    assert publication == '30 June 2026', 'Unexpected publication; review counts/version before importing'
    sheet = book['PSGC']
    headers = next(sheet.iter_rows(values_only=True))
    required = ['10-digit PSGC', 'Name', 'Correspondence Code', 'Geographic Level', 'Old names', 'City Class', 'Status']
    assert all(h in headers for h in required), 'Unexpected workbook headers'
    indexes = {h: headers.index(h) for h in required}
    def code(value, width=10):
        if value is None: return ''
        if isinstance(value, (int, float)):
            assert int(value) == value
            value = str(int(value))
        value = str(value).strip().zfill(width)
        assert re.fullmatch(r'\d{' + str(width) + r'}', value), 'Invalid PSGC code'
        return value
    nodes = {}
    for row in sheet.iter_rows(min_row=2, values_only=True):
        if row[0] is None: continue
        values = {h: row[i] for h, i in indexes.items()}
        ident = code(values['10-digit PSGC'])
        assert ident not in nodes, 'Duplicate PSGC code'
        level = values['Geographic Level'] or 'Group'
        assert level in ('Reg', 'Prov', 'City', 'Mun', 'Bgy', 'SubMun', 'Group')
        nodes[ident] = dict(id=ident, name=values['Name'], level=level,
            correspondence=code(values['Correspondence Code'],9), city_class=values['City Class'] or '',
            old_names=values['Old names'] or '', status=str(values['Status'] or ''))
    counts = Counter(n['level'] for n in nodes.values())
    assert counts == dict(Reg=18, Prov=82, City=149, Mun=1493, Bgy=42010, SubMun=14, Group=2), str(counts)
    for ident, n in nodes.items():
        region = ident[:2] + '00000000'
        prov = ident[:5] + '00000'
        if n['level'] == 'Reg': parent = ''
        elif n['level'] in ('Prov', 'Group'): parent = region
        elif n['level'] in ('City', 'Mun'):
            parent = prov if prov != ident and prov in nodes and nodes[prov]['level'] in ('Prov','Group') and n['city_class'] not in ('HUC','ICC') else region
        elif n['level'] == 'SubMun': parent = prov
        else: parent = ident[:7] + '000'
        assert not parent or parent in nodes, f'Missing parent {ident}: {parent}'
        n.update(parent=parent, region=region, province='', city='')
    for n in nodes.values():
        ancestor = n
        visited = set()
        while ancestor:
            assert ancestor['id'] not in visited, 'Cyclic hierarchy'
            visited.add(ancestor['id'])
            if ancestor['level'] == 'Prov': n['province'] = ancestor['id']
            if ancestor['level'] in ('City','Mun'): n['city'] = ancestor['id']
            ancestor = nodes.get(ancestor['parent'])
        assert nodes[n['region']]['level'] == 'Reg'
        if n['level'] == 'Bgy': assert n['city'], 'Barangay without city/municipality'
    metadata = dict(version='PSA PSGC as of 30 June 2026', source_file=Path(path).name,
        source_sha256=hashlib.sha256(Path(path).read_bytes()).hexdigest(), sheets=book.sheetnames,
        headers=list(headers), counts=dict(counts), total=len(nodes), geographic_total=len(nodes)-counts['Group'])
    return dict(metadata=metadata, locations=list(nodes.values()))

if __name__ == '__main__':
    result=prepare(sys.argv[1])
    Path(sys.argv[2]).write_text(json.dumps(result, ensure_ascii=False, separators=(',',':')), encoding='utf8')
    print(json.dumps(result['metadata'], ensure_ascii=False, indent=2))
