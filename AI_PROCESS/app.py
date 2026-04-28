import customtkinter as ctk
import google.generativeai as genai
import threading
import time
import re
from PIL import Image
from tkinter import filedialog, messagebox
import os
import sys
import datetime

# Enable ANSI escape codes for Windows CMD
os.system("color")

class Colors:
    HEADER = '\033[95m'
    OKBLUE = '\033[94m'
    OKCYAN = '\033[96m'
    OKGREEN = '\033[92m'
    WARNING = '\033[93m'
    FAIL = '\033[91m'
    ENDC = '\033[0m'
    BOLD = '\033[1m'
    UNDERLINE = '\033[4m'

# --- CONFIG ---
GEMINI_API_KEY = "AIzaSyCve4ReDIG4oWFZzA36sOp2o-I1nIq3Wxw"
genai.configure(api_key=GEMINI_API_KEY)

ctk.set_appearance_mode("Dark")
ctk.set_default_color_theme("blue")

class ProcessStep(ctk.CTkFrame):
    def __init__(self, master, title, desc, **kwargs):
        super().__init__(master, fg_color="#1f2937", border_color="#374151", border_width=2, **kwargs)
        self.title_lbl = ctk.CTkLabel(self, text=title, font=ctk.CTkFont(size=16, weight="bold"), text_color="#d1d5db")
        self.title_lbl.pack(anchor="w", padx=15, pady=(10, 0))
        
        self.desc_lbl = ctk.CTkLabel(self, text=desc, font=ctk.CTkFont(size=12), text_color="#9ca3af", justify="left")
        self.desc_lbl.pack(anchor="w", padx=15, pady=(2, 10))
        
        self.status_lbl = ctk.CTkLabel(self, text="Pending", font=ctk.CTkFont(size=12, weight="bold"), fg_color="#374151", corner_radius=5, text_color="#d1d5db")
        self.status_lbl.pack(side="right", padx=15, pady=10)

    def set_running(self):
        self.configure(border_color="#3b82f6")
        self.title_lbl.configure(text_color="#60a5fa")
        self.status_lbl.configure(text="Running...", fg_color="#1d4ed8", text_color="white")

    def set_passed(self, msg="Passed"):
        self.configure(border_color="#10b981")
        self.title_lbl.configure(text_color="#34d399")
        self.status_lbl.configure(text=msg, fg_color="#065f46", text_color="#a7f3d0")

    def set_failed(self, msg="Failed"):
        self.configure(border_color="#ef4444")
        self.title_lbl.configure(text_color="#f87171")
        self.status_lbl.configure(text=msg, fg_color="#991b1b", text_color="#fecaca")
        
    def reset(self):
        self.configure(border_color="#374151")
        self.title_lbl.configure(text_color="#d1d5db")
        self.status_lbl.configure(text="Pending", fg_color="#374151", text_color="#d1d5db")


class App(ctk.CTk):
    def __init__(self):
        super().__init__()
        self.title("AI Moderation Workflow Monitor")
        self.geometry("1000x700")
        self.configure(fg_color="#111827")
        
        # UI Layout
        self.grid_columnconfigure(0, weight=1)
        self.grid_columnconfigure(1, weight=2)
        self.grid_rowconfigure(0, weight=1)
        
        self.image_path = None
        
        self.setup_left_panel()
        self.setup_right_panel()
        
    def setup_left_panel(self):
        self.left_panel = ctk.CTkFrame(self, fg_color="#1f2937", corner_radius=0)
        self.left_panel.grid(row=0, column=0, sticky="nsew", padx=1, pady=1)
        
        lbl = ctk.CTkLabel(self.left_panel, text="User Input Simulation", font=ctk.CTkFont(size=20, weight="bold"), text_color="white")
        lbl.pack(pady=(30, 20), padx=20, anchor="w")
        
        # Text input
        ctk.CTkLabel(self.left_panel, text="1. Enter Donation Message/Name:", text_color="#9ca3af").pack(anchor="w", padx=20)
        self.text_input = ctk.CTkTextbox(self.left_panel, height=100, fg_color="#111827", text_color="white", border_color="#374151", border_width=1)
        self.text_input.pack(fill="x", padx=20, pady=(5, 20))
        
        # Image input
        ctk.CTkLabel(self.left_panel, text="2. Upload Donation Image (Optional):", text_color="#9ca3af").pack(anchor="w", padx=20)
        
        self.img_btn = ctk.CTkButton(self.left_panel, text="Select Image", fg_color="#374151", hover_color="#4b5563", command=self.select_image)
        self.img_btn.pack(anchor="w", padx=20, pady=5)
        
        self.img_preview_lbl = ctk.CTkLabel(self.left_panel, text="No image selected", text_color="#6b7280")
        self.img_preview_lbl.pack(anchor="w", padx=20, pady=5)
        
        # Run button
        self.run_btn = ctk.CTkButton(self.left_panel, text="SIMULATE MODERATION", font=ctk.CTkFont(weight="bold"), 
                                     fg_color="#0e7490", hover_color="#155e75", height=45, command=self.start_process)
        self.run_btn.pack(fill="x", side="bottom", padx=20, pady=30)
        
    def setup_right_panel(self):
        self.right_panel = ctk.CTkFrame(self, fg_color="#111827", corner_radius=0)
        self.right_panel.grid(row=0, column=1, sticky="nsew", padx=20, pady=20)
        
        header = ctk.CTkLabel(self.right_panel, text="AI Processing Pipeline", font=ctk.CTkFont(size=24, weight="bold"), text_color="white")
        header.pack(anchor="w", pady=(10, 30))
        
        self.step1 = ProcessStep(self.right_panel, "Bước 1: Tiếp nhận dữ liệu", "Trích xuất văn bản và định dạng hình ảnh")
        self.step1.pack(fill="x", pady=10)
        
        self.step2 = ProcessStep(self.right_panel, "Bước 2: Bộ lọc từ khóa (Local)", "Quét nhanh bằng Regex các từ ngữ cấm cơ bản")
        self.step2.pack(fill="x", pady=10)
        
        self.step3 = ProcessStep(self.right_panel, "Bước 3: Google Gemini AI", "Phân tích ngữ nghĩa chuyên sâu & nhận diện ảnh NSFW")
        self.step3.pack(fill="x", pady=10)
        
        self.step4 = ProcessStep(self.right_panel, "Bước 4: Quyết định cuối cùng", "Tổng hợp kết quả để Phê duyệt hoặc Từ chối")
        self.step4.pack(fill="x", pady=10)

        self.log_box = ctk.CTkTextbox(self.right_panel, height=150, fg_color="#000000", text_color="#34d399", font=ctk.CTkFont(family="Courier", size=12))
        self.log_box.pack(fill="both", expand=True, pady=(20, 0))
        self.log_box.insert("end", "Hệ thống sẵn sàng. Đang chờ dữ liệu đầu vào...\n")
        self.log_box.configure(state="disabled")

    def log(self, msg, level="INFO"):
        # UI logging
        self.log_box.configure(state="normal")
        self.log_box.insert("end", f"> {msg}\n")
        self.log_box.see("end")
        self.log_box.configure(state="disabled")
        self.update()
        
        # CMD logging
        now = datetime.datetime.now().strftime("%H:%M:%S")
        prefix = f"[{now}]"
        if level == "INFO":
            print(f"{Colors.OKCYAN}{prefix} [INFO] {msg}{Colors.ENDC}")
        elif level == "SUCCESS":
            print(f"{Colors.OKGREEN}{Colors.BOLD}{prefix} [SUCCESS] {msg}{Colors.ENDC}")
        elif level == "WARNING":
            print(f"{Colors.WARNING}{prefix} [WARNING] {msg}{Colors.ENDC}")
        elif level == "ERROR":
            print(f"{Colors.FAIL}{Colors.BOLD}{prefix} [ERROR] {msg}{Colors.ENDC}")
        else:
            print(f"{prefix} {msg}")

    def select_image(self):
        path = filedialog.askopenfilename(filetypes=[("Images", "*.jpg *.png *.jpeg *.gif")])
        if path:
            self.image_path = path
            self.img_preview_lbl.configure(text=os.path.basename(path), text_color="#34d399")
            
    def check_local_dict(self, text):
        patterns = [
            r'c\s*[aăặ]\s*[ck]', r'l\s*[oồôò]\s*n', r'đ\s*[iị]\s*t', r'd\s*[iị]\s*t',
            r'b\s*u\s*[oồò]\s*i', r'v\s*[ck]\s*l', r'd\s*[uụ]\s*m\s*[aá]', r'c\s*[uứ]\s*t', r'đ\s*[eéê]\s*o',
            r'f\s*[uü]\s*c\s*k', r's\s*h\s*[iì1!]\s*t', r'b\s*[iì1!]\s*t\s*c\s*h', r'd\s*[iì1!]\s*c\s*k',
            r'p\s*u\s*s\s*s\s*y', r'a\s*s\s*s', r'c\s*u\s*n\s*t', r'c\s*o\s*c\s*k', r'w\s*h\s*o\s*r\s*e',
            r'n\s*[i1!]\s*g\s*g\s*[ae]'
        ]
        text_lower = text.lower()
        for p in patterns:
            if re.search(p, text_lower):
                return True, p
        return False, None
        
    def check_gemini(self, text, image_path):
        try:
            model = genai.GenerativeModel("gemini-2.5-flash")
            contents = []
            
            prompt = "Bạn là hệ thống kiểm duyệt nội dung tiếng Việt. "
            if text:
                prompt += f"Kiểm tra đoạn văn bản sau: '{text}'. "
            if image_path:
                prompt += "Kiểm tra hình ảnh đính kèm. "
                
            prompt += "Nếu có chứa nội dung tục tĩu, chửi thề, khiêu dâm, 18+, hoặc sỉ nhục, hãy trả lời theo cú pháp: VI_PHAM|Lý do. Nếu an toàn, trả lời: AN_TOAN|OK"
            
            contents.append(prompt)
            if image_path:
                img = Image.open(image_path)
                contents.append(img)
                
            response = model.generate_content(contents)
            res_text = response.text.strip()
            if res_text.startswith("VI_PHAM"):
                reason = res_text.split("|")[1] if "|" in res_text else "Nội dung vi phạm"
                return True, reason
            return False, "Safe"
        except Exception as e:
            return True, f"Lỗi AI: {str(e)}"
            
    def start_process(self):
        text = self.text_input.get("1.0", "end").strip()
        img = self.image_path
        
        if not text and not img:
            messagebox.showwarning("Warning", "Please enter text or select an image to test.")
            return
            
        self.run_btn.configure(state="disabled")
        for step in [self.step1, self.step2, self.step3, self.step4]:
            step.reset()
            
        self.log_box.configure(state="normal")
        self.log_box.delete("1.0", "end")
        self.log_box.configure(state="disabled")
        
        threading.Thread(target=self.run_pipeline, args=(text, img), daemon=True).start()
        
    def run_pipeline(self, text, img):
        print(f"\n{Colors.HEADER}{Colors.BOLD}======================================================{Colors.ENDC}")
        print(f"{Colors.HEADER}{Colors.BOLD}   KHỞI ĐỘNG HỆ THỐNG KIỂM DUYỆT AI TỰ ĐỘNG   {Colors.ENDC}")
        print(f"{Colors.HEADER}{Colors.BOLD}======================================================{Colors.ENDC}\n")
        self.log("--- BẮT ĐẦU QUY TRÌNH KIỂM DUYỆT ---", "INFO")
        
        # Step 1
        self.step1.set_running()
        time.sleep(1)
        if text: self.log(f"Đã nhận nội dung văn bản: '{text}'", "INFO")
        if img: self.log(f"Đã nhận hình ảnh đính kèm: {os.path.basename(img)}", "INFO")
        self.step1.set_passed("Hoàn tất")
        self.log("Bước 1: Trích xuất dữ liệu thành công.", "SUCCESS")
        
        # Step 2
        self.step2.set_running()
        time.sleep(1)
        if text:
            is_local_toxic, pattern = self.check_local_dict(text)
            if is_local_toxic:
                self.log(f"PHÁT HIỆN VI PHẠM (Bộ lọc cục bộ)! Từ khóa: {pattern}", "ERROR")
                self.step2.set_failed("Vi phạm")
                self.finish_pipeline(False, f"Từ ngữ vi phạm (Chặn bởi Local Regex)")
                return
            else:
                self.log("Bộ lọc từ khóa nhanh: An toàn.", "SUCCESS")
                self.step2.set_passed("An toàn")
        else:
            self.log("Không có văn bản để kiểm tra nhanh.", "INFO")
            self.step2.set_passed("Bỏ qua")
            
        # Step 3
        self.step3.set_running()
        self.log("Đang gửi dữ liệu phân tích chuyên sâu đến máy chủ Google Gemini AI...", "INFO")
        is_gemini_toxic, reason = self.check_gemini(text, img)
        if is_gemini_toxic:
            self.log(f"CẢNH BÁO TỪ GEMINI AI: {reason}", "ERROR")
            self.step3.set_failed("Vi phạm")
            self.finish_pipeline(False, reason)
            return
        else:
            self.log("Google Gemini AI phân tích: An toàn.", "SUCCESS")
            self.step3.set_passed("An toàn")
            
        # Step 4
        self.finish_pipeline(True, "Đã phê duyệt")
        
    def finish_pipeline(self, passed, reason):
        self.step4.set_running()
        time.sleep(0.5)
        if passed:
            self.step4.set_passed("HỢP LỆ")
            self.log("KẾT LUẬN: Nội dung HỢP LỆ và an toàn. Được phép tiếp tục.", "SUCCESS")
        else:
            self.step4.set_failed("TỪ CHỐI")
            self.log(f"KẾT LUẬN: Nội dung BỊ TỪ CHỐI. Lý do: {reason}", "ERROR")
            
        self.log("--- KẾT THÚC QUY TRÌNH KIỂM DUYỆT ---", "INFO")
        print(f"\n{Colors.HEADER}======================================================{Colors.ENDC}\n")
        self.run_btn.configure(state="normal")

if __name__ == "__main__":
    app = App()
    app.mainloop()
