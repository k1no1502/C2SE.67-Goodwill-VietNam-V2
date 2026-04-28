import openpyxl
from openpyxl import Workbook
from openpyxl.worksheet.table import Table, TableStyleInfo
from openpyxl.worksheet.datavalidation import DataValidation

wb = Workbook()
ws = wb.active
ws.title = 'Data'
cols = ['STT', 'Tên Vật Phẩm', 'Danh Mục', 'Mô Tả Chi Tiết', 'Số Lượng', 'Đơn Vị', 'Tình Trạng', 'Giá Trị Ước Tính (vnd)', 'Hình ảnh (URL)']
ws.append(cols)
ws.append(['=ROW()-1', 'Quần áo cũ', 'Quần áo', 'Áo sơ mi nam size L', 5, 'cái', 'Tốt', 50000, ''])

ws.column_dimensions['B'].width = 20
ws.column_dimensions['C'].width = 15
ws.column_dimensions['D'].width = 30
ws.column_dimensions['H'].width = 20

tab = Table(displayName='Table1', ref='A1:I2')
style = TableStyleInfo(name='TableStyleMedium9', showFirstColumn=False, showLastColumn=False, showRowStripes=True, showColumnStripes=False)
tab.tableStyleInfo = style
ws.add_table(tab)

# Add dropdowns
dv_cat = DataValidation(type='list', formula1='"Quần áo,Điện tử,Sách,Gia dụng,Đồ chơi,Thực phẩm,Y tế,Khác,Điện lạnh,Đồ gia dụng,Nhà bếp,Vệ sinh"', allow_blank=True)
dv_unit = DataValidation(type='list', formula1='"cái,bộ,kg,cuốn,thùng"', allow_blank=True)
dv_cond = DataValidation(type='list', formula1='"Mới,Như mới,Tốt,Khá,Cũ"', allow_blank=True)

ws.add_data_validation(dv_cat)
ws.add_data_validation(dv_unit)
ws.add_data_validation(dv_cond)

dv_cat.add('C2:C1000')
dv_unit.add('F2:F1000')
dv_cond.add('G2:G1000')

wb.save('c:/xampp/htdocs/assets/excel/donation_template.xlsx')
print("Saved")
