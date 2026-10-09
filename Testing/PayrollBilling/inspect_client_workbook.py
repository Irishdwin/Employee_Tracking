"""Read-only formula/layout inventory. Never writes either source workbook."""
import hashlib
import json
from pathlib import Path
import openpyxl
from openpyxl.formula.translate import Translator

source = Path(r'C:\Users\Irishdwin Gamutan\OneDrive\Desktop\Onazul Capstone\files\SAMPLE.xlsx')
template = Path(__file__).resolve().parents[2] / 'Templates' / 'SAMPLE.xlsx'
book = openpyxl.load_workbook(source, data_only=False)
report = {'source_sha256': hashlib.sha256(source.read_bytes()).hexdigest(),
          'template_identical': source.read_bytes() == template.read_bytes(), 'worksheets': []}
for sheet in book:
    formulas = {cell.coordinate: cell.value for row in sheet for cell in row if cell.data_type == 'f'}
    differences = []
    for row in range(7, 16):
        for col in range(1, sheet.max_column + 1):
            baseline, current = sheet.cell(6, col), sheet.cell(row, col)
            if baseline.data_type == 'f':
                expected = Translator(baseline.value, origin=baseline.coordinate).translate_formula(current.coordinate)
                if current.value != expected:
                    differences.append({'cell': current.coordinate, 'expected': expected, 'actual': current.value})
    report['worksheets'].append({'name': sheet.title, 'dimensions': sheet.calculate_dimension(),
        'employee_rows': '6:15', 'unnamed_formula_row': 16, 'total_row': 17,
        'summary_rows': '22:45', 'employee_formula_exceptions': differences,
        'row_16_has_identity_or_rate': any(sheet.cell(16, col).value is not None for col in range(2, 6)),
        'formulas': formulas, 'merged_ranges': [str(r) for r in sheet.merged_cells.ranges],
        'used_cells': {c.coordinate: {'value': c.value, 'type': c.data_type,
            'style_id': c.style_id, 'number_format': c.number_format,
            'alignment': str(c.alignment), 'border': str(c.border)}
            for row in sheet for c in row if c.value is not None},
        'columns': {key: {'width': dim.width, 'hidden': dim.hidden} for key, dim in sheet.column_dimensions.items()},
        'rows': {key: {'height': dim.height, 'hidden': dim.hidden} for key, dim in sheet.row_dimensions.items()},
        'orientation': sheet.page_setup.orientation, 'paper_size': sheet.page_setup.paperSize,
        'conditional_formatting_count': len(sheet.conditional_formatting),
        'print_area': str(sheet.print_area), 'print_titles': sheet.print_title_rows,
        'formula_error_literals': [c.coordinate for row in sheet for c in row if c.data_type == 'e']})
target = Path(__file__).with_name('client_workbook_inventory.json')
target.write_text(json.dumps(report, indent=2), encoding='utf-8')
print(json.dumps({'template_identical': report['template_identical'],
    'worksheets': [{k: v for k, v in s.items() if k not in ('formulas', 'merged_ranges', 'used_cells', 'columns', 'rows')} for s in report['worksheets']]}, indent=2))
