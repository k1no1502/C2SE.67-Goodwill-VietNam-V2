import tkinter as tk
from tkinter import ttk, messagebox
import customtkinter as ctk
import pymysql
import requests
import json
import hashlib
import hmac
import time
import webbrowser
from PIL import Image, ImageTk
import os
import cv2
import threading
import sys

# --- Config ---
MOMO_CONFIG = {
    'partner_code': 'MOMOBKUN20180529',
    'access_key': 'klm05TvNBzhg7h7j',
    'secret_key': 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa',
    'endpoint': 'https://test-payment.momo.vn/v2/gateway/api/create'
}

DB_CONFIG = {
    'host': 'localhost', 'user': 'root', 'password': '',
    'database': 'goodwill_vietnam', 'charset': 'utf8mb4',
    'cursorclass': pymysql.cursors.DictCursor
}

class CashierApp:
    def __init__(self, root):
        self.root = root
        self.root.title("Goodwill Vietnam POS - Modern Version")
        self.root.geometry("1200x850")
        self.root.configure(fg_color="#111827")

        self.cart = []
        self.products = []
        self.db = None
        self.scanning = False
        self.current_total = 0
        
        # Detectors (Standard OpenCV - NO PYZBAR)
        self.qr_detector = cv2.QRCodeDetector()
        try:
            self.barcode_detector = cv2.barcode.BarcodeDetector()
        except:
            self.barcode_detector = None

        self.setup_styles()
        self.connect_db()
        self.create_ui()
        self.load_products()

    def setup_styles(self):
        style = ttk.Style()
        style.theme_use('clam')
        style.configure("Treeview", 
                        background="#1f2937", 
                        foreground="white", 
                        fieldbackground="#1f2937", 
                        rowheight=40, 
                        font=('Arial', 11))
        style.map('Treeview', background=[('selected', '#0ea5e9')])
        style.configure("Treeview.Heading", 
                        background="#374151", 
                        foreground="white", 
                        font=('Arial', 12, 'bold'))

    def connect_db(self):
        try:
            self.db = pymysql.connect(**DB_CONFIG)
        except Exception as e:
            messagebox.showerror("Lỗi", f"DB Error: {e}")

    def create_ui(self):
        # Header
        header = ctk.CTkFrame(self.root, fg_color="#0e7490", corner_radius=0, height=70)
        header.pack(fill="x")
        ctk.CTkLabel(header, text="GOODWILL VIETNAM POS", font=ctk.CTkFont("Arial", 24, "bold"), text_color="white").pack(pady=15)

        main_f = ctk.CTkFrame(self.root, fg_color="#111827", corner_radius=0)
        main_f.pack(fill="both", expand=True, padx=20, pady=20)

        # Left: Products
        left = ctk.CTkFrame(main_f, fg_color="#1f2937", corner_radius=10)
        left.pack(side="left", fill="both", expand=True, padx=(0, 10))
        
        lbl_left = ctk.CTkLabel(left, text="Quản lý Sản phẩm", font=ctk.CTkFont("Arial", 18, "bold"), text_color="#34d399")
        lbl_left.pack(anchor="w", padx=20, pady=(15, 5))

        sf = ctk.CTkFrame(left, fg_color="transparent")
        sf.pack(fill="x", padx=20, pady=(0, 15))
        self.search_var = ctk.StringVar()
        self.search_var.trace_add("write", lambda *args: self.filter_products())
        
        ctk.CTkEntry(sf, textvariable=self.search_var, font=ctk.CTkFont("Arial", 14), placeholder_text="Tìm kiếm sản phẩm...").pack(side="left", fill="x", expand=True, padx=(0, 15))
        
        self.cam_btn = ctk.CTkButton(sf, text="📷 BẬT CAMERA QUÉT", font=ctk.CTkFont("Arial", 12, "bold"), fg_color="#0ea5e9", hover_color="#0284c7", command=self.toggle_camera)
        self.cam_btn.pack(side="right")

        tree_frame = ctk.CTkFrame(left, fg_color="transparent")
        tree_frame.pack(fill="both", expand=True, padx=20, pady=(0, 20))
        
        self.tree = ttk.Treeview(tree_frame, columns=("ID", "Name", "Price", "Stock"), show="headings")
        self.tree.heading("ID", text="ID"); self.tree.heading("Name", text="Tên"); self.tree.heading("Price", text="Giá"); self.tree.heading("Stock", text="Tồn")
        self.tree.column("ID", width=50); self.tree.column("Name", width=300); self.tree.column("Price", width=120); self.tree.column("Stock", width=80)
        self.tree.pack(side="left", fill="both", expand=True)
        
        scrollbar = ttk.Scrollbar(tree_frame, orient="vertical", command=self.tree.yview)
        scrollbar.pack(side="right", fill="y")
        self.tree.configure(yscrollcommand=scrollbar.set)
        
        self.tree.bind("<Double-1>", self.add_to_cart_event)

        # Right: Cart
        right = ctk.CTkFrame(main_f, fg_color="#1f2937", corner_radius=10, width=420)
        right.pack(side="right", fill="both")

        lbl_right = ctk.CTkLabel(right, text="Giỏ hàng", font=ctk.CTkFont("Arial", 18, "bold"), text_color="#f87171")
        lbl_right.pack(anchor="w", padx=20, pady=(15, 5))

        cart_frame = ctk.CTkFrame(right, fg_color="transparent")
        cart_frame.pack(fill="both", expand=True, padx=20, pady=(0, 15))

        self.cart_tree = ttk.Treeview(cart_frame, columns=("Name", "Qty", "Total"), show="headings")
        self.cart_tree.heading("Name", text="Sản phẩm"); self.cart_tree.heading("Qty", text="SL"); self.cart_tree.heading("Total", text="Tổng")
        self.cart_tree.column("Name", width=200); self.cart_tree.column("Qty", width=50); self.cart_tree.column("Total", width=120)
        self.cart_tree.pack(side="left", fill="both", expand=True)
        
        cart_scroll = ttk.Scrollbar(cart_frame, orient="vertical", command=self.cart_tree.yview)
        cart_scroll.pack(side="right", fill="y")
        self.cart_tree.configure(yscrollcommand=cart_scroll.set)

        summary = ctk.CTkFrame(right, fg_color="#111827", corner_radius=10)
        summary.pack(fill="x", padx=20, pady=(0, 20))
        self.total_label = ctk.CTkLabel(summary, text="TỔNG: 0đ", font=ctk.CTkFont("Arial", 26, "bold"), text_color="#34d399")
        self.total_label.pack(pady=20)

        btn_f = ctk.CTkFrame(right, fg_color="transparent")
        btn_f.pack(fill="x", padx=20, pady=(0, 20))
        
        ctk.CTkButton(btn_f, text="💵 THANH TOÁN TIỀN MẶT", font=ctk.CTkFont("Arial", 14, "bold"), fg_color="#16a34a", hover_color="#15803d", height=50, command=self.pay_cash).pack(fill="x", pady=5)
        ctk.CTkButton(btn_f, text="📱 THANH TOÁN MOMO", font=ctk.CTkFont("Arial", 14, "bold"), fg_color="#be185d", hover_color="#9d174d", height=50, command=self.pay_momo).pack(fill="x", pady=5)
        ctk.CTkButton(btn_f, text="🗑 HỦY GIỎ HÀNG", font=ctk.CTkFont("Arial", 14, "bold"), fg_color="#4b5563", hover_color="#374151", height=40, command=self.clear_cart).pack(fill="x", pady=(15, 0))

    # --- Camera Logic ---
    def toggle_camera(self):
        if self.scanning:
            self.scanning = False
            self.cam_btn.configure(text="📷 BẬT CAMERA QUÉT", fg_color="#0ea5e9", hover_color="#0284c7")
        else:
            self.scanning = True
            self.cam_btn.configure(text="⏹ DỪNG QUÉT", fg_color="#ef4444", hover_color="#b91c1c")
            threading.Thread(target=self.camera_thread, daemon=True).start()

    def camera_thread(self):
        cap = cv2.VideoCapture(0)
        if not cap.isOpened():
            messagebox.showerror("Lỗi", "Không mở được Camera."); self.scanning = False
            self.root.after(0, lambda: self.cam_btn.configure(text="📷 BẬT CAMERA QUÉT", fg_color="#0ea5e9", hover_color="#0284c7"))
            return

        cv2.namedWindow("POS Scanner")
        cv2.setWindowProperty("POS Scanner", cv2.WND_PROP_TOPMOST, 1)

        while self.scanning:
            ret, frame = cap.read()
            if not ret: break
            
            # QR Code
            ok, info, pts, _ = self.qr_detector.detectAndDecodeMulti(frame)
            if ok:
                for i in info:
                    if i: self.root.after(0, lambda c=i: self.handle_code(c)); time.sleep(1.5)
            
            # Barcode (Standard OpenCV)
            if self.barcode_detector:
                try:
                    ok_bc, codes, types, pts_bc = self.barcode_detector.detectAndDecode(frame)
                    if ok_bc:
                        for c in codes:
                            if c: self.root.after(0, lambda c=c: self.handle_code(c)); time.sleep(1.5)
                except: pass

            cv2.imshow("POS Scanner", frame)
            if cv2.waitKey(1) & 0xFF == 27: break
        
        cap.release(); cv2.destroyAllWindows(); self.scanning = False
        self.root.after(0, lambda: self.cam_btn.configure(text="📷 BẬT CAMERA QUÉT", fg_color="#0ea5e9", hover_color="#0284c7"))

    def handle_code(self, code):
        code = str(code).strip()
        item_id = None
        if code.startswith("GWV"): 
            try: item_id = int(code[3:])
            except: pass
        else:
            try: item_id = int(code)
            except: pass
        if item_id:
            p = next((x for x in self.products if x['item_id'] == item_id), None)
            if p: self.add_to_cart(p)

    def load_products(self):
        if not self.db: return
        try:
            with self.db.cursor() as cursor:
                cursor.execute("SELECT item_id, name, sale_price, quantity FROM inventory WHERE status='available' AND is_for_sale=1")
                self.products = cursor.fetchall()
            self.display_products(self.products)
        except: pass

    def display_products(self, data):
        for i in self.tree.get_children(): self.tree.delete(i)
        for p in data: self.tree.insert("", "end", values=(p['item_id'], p['name'], f"{p['sale_price']:,}đ", p['quantity']))

    def filter_products(self):
        q = self.search_var.get().lower()
        f = [p for p in self.products if q in p['name'].lower() or q in str(p['item_id'])]
        self.display_products(f)

    def add_to_cart_event(self, event):
        sel = self.tree.selection()
        if sel:
            p = next((x for x in self.products if x['item_id'] == int(self.tree.item(sel, "values")[0])), None)
            if p: self.add_to_cart(p)

    def add_to_cart(self, p):
        ex = next((i for i in self.cart if i['item_id'] == p['item_id']), None)
        if ex:
            if ex['quantity'] < p['quantity']: ex['quantity'] += 1
        else: self.cart.append({'item_id': p['item_id'], 'name': p['name'], 'price': p['sale_price'], 'quantity': 1})
        self.render_cart()

    def render_cart(self):
        for i in self.cart_tree.get_children(): self.cart_tree.delete(i)
        total = sum(i['price'] * i['quantity'] for i in self.cart)
        for i in self.cart: self.cart_tree.insert("", "end", values=(i['name'], i['quantity'], f"{i['price']*i['quantity']:,}đ"))
        self.total_label.configure(text=f"TỔNG: {total:,}đ"); self.current_total = total

    def clear_cart(self): self.cart = []; self.render_cart()

    def pay_cash(self):
        if not self.cart: return
        cash_win = ctk.CTkToplevel(self.root)
        cash_win.title("Thanh toán tiền mặt"); cash_win.geometry("450x400"); cash_win.grab_set()
        cash_win.configure(fg_color="#1f2937")
        
        f = ctk.CTkFrame(cash_win, fg_color="transparent")
        f.pack(fill="both", expand=True, padx=20, pady=20)
        
        ctk.CTkLabel(f, text=f"TỔNG: {self.current_total:,}đ", font=ctk.CTkFont("Arial", 24, "bold"), text_color="#34d399").pack(pady=15)
        ctk.CTkLabel(f, text="TIỀN KHÁCH ĐƯA:", font=ctk.CTkFont("Arial", 16), text_color="white").pack()
        
        rv = ctk.StringVar()
        re = ctk.CTkEntry(f, textvariable=rv, font=ctk.CTkFont("Arial", 20), justify="center", height=45)
        re.pack(fill="x", pady=15)
        re.focus_set()
        
        cl = ctk.CTkLabel(f, text="TRẢ LẠI: 0đ", font=ctk.CTkFont("Arial", 18, "bold"), text_color="#f87171")
        cl.pack(pady=15)
        
        self._updating_rv = False
        def u(*a):
            if self._updating_rv: return
            self._updating_rv = True
            
            raw_val = rv.get().replace(",", "").replace(".", "")
            if raw_val.isdigit():
                formatted = f"{int(raw_val):,}"
                rv.set(formatted)
                re.icursor("end")
            elif raw_val == "":
                rv.set("")
            else:
                clean = ''.join(c for c in raw_val if c.isdigit())
                if clean:
                    formatted = f"{int(clean):,}"
                    rv.set(formatted)
                    re.icursor("end")
                else:
                    rv.set("")
            
            try:
                r = int(rv.get().replace(",", ""))
                c = r - self.current_total
                cl.configure(text=f"TRẢ LẠI: {c:,}đ" if c >= 0 else "Chưa đủ")
            except: 
                cl.configure(text="TRẢ LẠI: 0đ")
                
            self._updating_rv = False

        rv.trace_add("write", u)
        
        def ok():
            cash_win.destroy(); self.process_order("cash", True)
            
        ctk.CTkButton(f, text="XÁC NHẬN THANH TOÁN", font=ctk.CTkFont("Arial", 16, "bold"), fg_color="#16a34a", hover_color="#15803d", height=45, command=ok).pack(fill="x", pady=(10, 0))

    def pay_momo(self):
        if not self.cart: return
        oid = self.create_pending_order("momo")
        if not oid: return
        m_oid = f"PY_{oid}_{int(time.time())}"; amt = str(int(self.current_total))
        ipn = "http://localhost/api/momo_notify.php"; red = "http://localhost/admin/cashier-direct-sale.php"
        raw = f"accessKey={MOMO_CONFIG['access_key']}&amount={amt}&extraData=&ipnUrl={ipn}&orderId={m_oid}&orderInfo=POS&partnerCode={MOMO_CONFIG['partner_code']}&redirectUrl={red}&requestId={m_oid}&requestType=captureWallet"
        sig = hmac.new(MOMO_CONFIG['secret_key'].encode('utf-8'), raw.encode('utf-8'), hashlib.sha256).hexdigest()
        payload = {'partnerCode': MOMO_CONFIG['partner_code'], 'requestId': m_oid, 'amount': amt, 'orderId': m_oid, 'orderInfo': "POS", 'redirectUrl': red, 'ipnUrl': ipn, 'requestType': 'captureWallet', 'signature': sig}
        try:
            r = requests.post(MOMO_CONFIG['endpoint'], json=payload).json()
            if r.get('resultCode') == 0: webbrowser.open(r['payUrl']); self.start_polling(oid)
            else: messagebox.showerror("Momo", r.get('message'))
        except: pass

    def create_pending_order(self, method):
        try:
            with self.db.cursor() as cursor:
                cursor.execute("INSERT INTO orders (shipping_name, payment_method, payment_status, total_amount, status, created_at) VALUES (%s, %s, 'pending', %s, 'pending', NOW())", ("POS", method, self.current_total))
                oid = self.db.insert_id()
                for i in self.cart: cursor.execute("INSERT INTO order_items (order_id, item_id, item_name, quantity, price, subtotal) VALUES (%s, %s, %s, %s, %s, %s)", (oid, i['item_id'], i['name'], i['quantity'], i['price'], i['price']*i['quantity']))
            self.db.commit(); return oid
        except: return None

    def start_polling(self, oid):
        def p():
            for _ in range(60):
                try:
                    with self.db.cursor() as cursor:
                        cursor.execute("SELECT payment_status FROM orders WHERE order_id = %s", (oid,))
                        if cursor.fetchone()['payment_status'] == 'paid': self.root.after(0, lambda: self.on_success(oid)); return
                except: pass
                time.sleep(5)
        threading.Thread(target=p, daemon=True).start()

    def on_success(self, oid):
        try:
            with self.db.cursor() as cursor:
                for i in self.cart: cursor.execute("UPDATE inventory SET quantity = quantity - %s WHERE item_id = %s", (i['quantity'], i['item_id']))
                cursor.execute("UPDATE orders SET status = 'delivered', payment_status = 'paid' WHERE order_id = %s", (oid,))
            self.db.commit()
        except: pass
        self.clear_cart()
        self.load_products()
        self.show_success_window(oid)

    def process_order(self, method, is_paid):
        oid = self.create_pending_order(method)
        if oid and is_paid:
            try:
                with self.db.cursor() as cursor:
                    cursor.execute("UPDATE orders SET payment_status = 'paid', status = 'delivered' WHERE order_id = %s", (oid,))
                    for i in self.cart: cursor.execute("UPDATE inventory SET quantity = quantity - %s WHERE item_id = %s", (i['quantity'], i['item_id']))
                self.db.commit()
                self.clear_cart()
                self.load_products()
                self.show_success_window(oid)
            except: pass

    def show_success_window(self, oid):
        win = ctk.CTkToplevel(self.root)
        win.title("Thành công")
        win.geometry("400x250")
        win.attributes('-topmost', True)
        win.configure(fg_color="#1f2937")
        
        ctk.CTkLabel(win, text="✅ THANH TOÁN THÀNH CÔNG!", font=ctk.CTkFont("Arial", 20, "bold"), text_color="#10b981").pack(pady=(30, 10))
        ctk.CTkLabel(win, text=f"Mã đơn hàng: #{oid}", font=ctk.CTkFont("Arial", 16), text_color="white").pack(pady=5)
        
        def print_btn():
            self.print_receipt(oid)
            win.destroy()
            
        ctk.CTkButton(win, text="🖨️ IN BILL", font=ctk.CTkFont("Arial", 14, "bold"), fg_color="#0ea5e9", hover_color="#0284c7", height=45, command=print_btn).pack(pady=20)

    def print_receipt(self, oid):
        from fpdf import FPDF
        
        pdf = FPDF()
        pdf.add_page()
        try:
            pdf.add_font('Arial', '', 'c:/windows/fonts/arial.ttf', uni=True)
            pdf.add_font('Arial', 'B', 'c:/windows/fonts/arialbd.ttf', uni=True)
            pdf.set_font('Arial', 'B', 20)
        except:
            pdf.set_font('Arial', 'B', 20)
            
        pdf.cell(0, 10, 'GOODWILL VIETNAM - HÓA ĐƠN MUA HÀNG', ln=1, align='C')
        pdf.set_font('Arial', '', 12)
        pdf.cell(0, 10, f'Mã đơn hàng: #{oid}  |  Ngày: {time.strftime("%d/%m/%Y %H:%M")}', ln=1, align='C')
        pdf.cell(0, 10, '-'*80, ln=1, align='C')
        
        pdf.set_font('Arial', 'B', 12)
        pdf.cell(100, 10, 'Sản phẩm', border=0)
        pdf.cell(30, 10, 'SL', border=0, align='C')
        pdf.cell(60, 10, 'Thành tiền', border=0, align='R')
        pdf.ln(10)
        
        pdf.set_font('Arial', '', 12)
        try:
            with self.db.cursor() as cursor:
                cursor.execute("SELECT item_name, quantity, subtotal FROM order_items WHERE order_id = %s", (oid,))
                items = cursor.fetchall()
                cursor.execute("SELECT total_amount FROM orders WHERE order_id = %s", (oid,))
                total_order = cursor.fetchone()['total_amount']
                
            for i in items:
                pdf.cell(100, 10, i['item_name'][:40], border=0)
                pdf.cell(30, 10, str(i['quantity']), border=0, align='C')
                pdf.cell(60, 10, f"{int(i['subtotal']):,}đ", border=0, align='R')
                pdf.ln(8)
                
            pdf.cell(0, 10, '-'*80, ln=1, align='C')
            pdf.set_font('Arial', 'B', 16)
            pdf.cell(130, 10, 'TỔNG CỘNG:', border=0, align='R')
            pdf.cell(60, 10, f"{int(total_order):,}đ", border=0, align='R')
        except:
            pdf.cell(0, 10, 'Lỗi tải chi tiết đơn hàng', ln=1)

        pdf_path = os.path.abspath(f"receipt_{oid}.pdf")
        try:
            pdf.output(pdf_path)
            
            import subprocess
            chrome_paths = [
                r"C:\Program Files\Google\Chrome\Application\chrome.exe",
                r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
                os.path.expandvars(r"%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe")
            ]
            opened = False
            for p in chrome_paths:
                if os.path.exists(p):
                    subprocess.Popen([p, pdf_path])
                    opened = True
                    break
            
            if not opened:
                os.startfile(pdf_path)
        except Exception as e:
            messagebox.showerror("Lỗi", f"Không thể tạo hoặc mở file PDF: {e}")

if __name__ == "__main__":
    ctk.set_appearance_mode("Dark")
    ctk.set_default_color_theme("blue")
    root = ctk.CTk()
    app = CashierApp(root)
    root.mainloop()
