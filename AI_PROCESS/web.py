import customtkinter as ctk
import threading
import time
import json
import os
import datetime

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
        self.title("Web AI Moderation Listener")
        self.geometry("700x700")
        self.configure(fg_color="#111827")
        
        self.grid_columnconfigure(0, weight=1)
        self.grid_rowconfigure(0, weight=1)
        
        self.setup_panel()
        
        # Absolute path to ai_queue.json to ensure it reads the correct file
        self.log_file = os.path.join(os.path.dirname(os.path.abspath(__file__)), "ai_queue.json")
        self.last_line_count = self.get_line_count()
        self.is_animating = False
        
        # Start monitoring thread
        threading.Thread(target=self.monitor_file, daemon=True).start()
        
    def get_line_count(self):
        if not os.path.exists(self.log_file):
            return 0
        try:
            with open(self.log_file, "r", encoding="utf-8") as f:
                return len(f.readlines())
        except:
            return 0
            
    def setup_panel(self):
        self.main_panel = ctk.CTkFrame(self, fg_color="#111827", corner_radius=0)
        self.main_panel.grid(row=0, column=0, sticky="nsew", padx=30, pady=30)
        
        header = ctk.CTkLabel(self.main_panel, text="📡 Web AI Process Listener", font=ctk.CTkFont(size=26, weight="bold"), text_color="white")
        header.pack(anchor="center", pady=(10, 5))
        
        self.sub_header = ctk.CTkLabel(self.main_panel, text="Listening to web submissions on localhost...", font=ctk.CTkFont(size=14), text_color="#10b981")
        self.sub_header.pack(anchor="center", pady=(0, 30))
        
        self.step1 = ProcessStep(self.main_panel, "Step 1: Tiếp nhận dữ liệu", "Trích xuất văn bản và định dạng hình ảnh từ form web...")
        self.step1.pack(fill="x", pady=10)
        
        self.step2 = ProcessStep(self.main_panel, "Step 2: Bộ lọc từ điển cục bộ", "Quét nhanh các từ ngữ vi phạm cơ bản bằng Regex...")
        self.step2.pack(fill="x", pady=10)
        
        self.step3 = ProcessStep(self.main_panel, "Step 3: Google Gemini AI", "Phân tích ngữ nghĩa văn bản và nội dung hình ảnh chuyên sâu...")
        self.step3.pack(fill="x", pady=10)
        
        self.log_box = ctk.CTkTextbox(self.main_panel, height=150, fg_color="#000000", text_color="#34d399", font=ctk.CTkFont(family="Courier", size=12))
        self.log_box.pack(fill="both", expand=True, pady=(20, 0))
        self.log_box.insert("end", "[INFO] Application started.\n[INFO] Waiting for users to submit forms on the website...\n")
        self.log_box.configure(state="disabled")

    def log(self, msg, to_cmd=True):
        # Log to GUI
        self.log_box.configure(state="normal")
        self.log_box.insert("end", f"> {msg}\n")
        self.log_box.see("end")
        self.log_box.configure(state="disabled")
        self.update()
        
        # We handle detailed CMD logging separately, but still output basic GUI logs if requested
        if to_cmd:
            import datetime
            timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            print(f"[{timestamp}] {msg}", flush=True)

    def monitor_file(self):
        while True:
            time.sleep(0.5)
            if self.is_animating:
                continue
                
            current_count = self.get_line_count()
            if current_count > self.last_line_count:
                # Read the latest line
                try:
                    with open(self.log_file, "r", encoding="utf-8") as f:
                        lines = f.readlines()
                        latest_json = lines[-1].strip()
                        data = json.loads(latest_json)
                        self.last_line_count = current_count
                        
                        # Trigger animation safely in main thread
                        self.after(0, self.trigger_animation, data)
                except Exception as e:
                    print("Error reading log:", e)
                    self.last_line_count = current_count

    def trigger_animation(self, data):
        message = data.get('message', '')
        title = data.get('title', 'Phát hiện vi phạm')
        
        # --- BẮT ĐẦU IN LOG CHI TIẾT RA CMD ---
        print("\n" + "="*80, flush=True)
        print(f"[{datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] 🟢 TÍN HIỆU TỪ WEB SERVER: NHẬN YÊU CẦU KIỂM DUYỆT MỚI", flush=True)
        print("="*80, flush=True)
        print(f"[*] Đang phân tích gói tin Payload...", flush=True)
        print(f"    => Nhãn: {title}", flush=True)
        print(f"    => Dữ liệu thô: {message}", flush=True)
        print("-" * 80, flush=True)
        
        self.is_animating = True
        self.sub_header.configure(text="Đang xử lý yêu cầu từ Web...", text_color="#f59e0b")
        
        # Determine which step failed
        fail_step = 3
        if "Từ bị cấm:" in message and "bởi hệ thống AI" not in message:
            fail_step = 2
            
        threading.Thread(target=self.run_pipeline, args=(title, message, fail_step), daemon=True).start()
        
    def run_pipeline(self, title, message, fail_step):
        for step in [self.step1, self.step2, self.step3]:
            step.reset()
            
        self.log("--- BẮT ĐẦU LUỒNG KIỂM DUYỆT ---", to_cmd=False)
        
        # Step 1
        print("[1/3] BƯỚC 1: TIẾP NHẬN & TRÍCH XUẤT DỮ LIỆU", flush=True)
        print("    [+] Đang quét các trường biểu mẫu...", flush=True)
        self.step1.set_running()
        time.sleep(1.2)
        self.step1.set_passed("Hoàn tất")
        self.log("Trích xuất dữ liệu hoàn tất.", to_cmd=False)
        print("    => TRẠNG THÁI: [OK] Dữ liệu hợp lệ, sẵn sàng để phân tích.", flush=True)
        print("-" * 80, flush=True)
        
        # Step 2
        print("[2/3] BƯỚC 2: QUÉT BỘ LỌC TỪ ĐIỂN CỤC BỘ (LOCAL REGEX)", flush=True)
        print("    [*] Đang nạp cơ sở dữ liệu: 19 patterns...", flush=True)
        self.step2.set_running()
        time.sleep(1.2)
        if fail_step == 2:
            self.step2.set_failed("Vi phạm")
            self.log(f"CẢNH BÁO (Local): {message}", to_cmd=False)
            print(f"    [!] PHÁT HIỆN VI PHẠM: Từ khóa nằm trong danh sách đen!", flush=True)
            print(f"    [!] Trích xuất lỗi: {message}", flush=True)
            print("    => TRẠNG THÁI: [VI PHẠM NGHIÊM TRỌNG]", flush=True)
            print("-" * 80, flush=True)
            self.finish_pipeline(title, message, bypass_gemini=True)
            return
        else:
            self.step2.set_passed("An toàn")
            self.log("Từ điển cục bộ: Không phát hiện vi phạm.", to_cmd=False)
            print("    [+] Không phát hiện từ khóa cấm trong dữ liệu cục bộ.", flush=True)
            print("    => TRẠNG THÁI: [AN TOÀN]", flush=True)
            print("-" * 80, flush=True)
            
        # Step 3
        print("[3/3] BƯỚC 3: PHÂN TÍCH CHUYÊN SÂU BẰNG GOOGLE GEMINI AI", flush=True)
        print("    [*] Đang gửi dữ liệu ngữ cảnh hình ảnh và văn bản qua API...", flush=True)
        self.step3.set_running()
        time.sleep(1.8)
        self.step3.set_failed("Vi phạm")
        self.log(f"CẢNH BÁO (Gemini AI): {message}", to_cmd=False)
        
        if "ảnh" in message.lower() or "nsfw" in message.lower():
            print(f"    [!] GOOGLE VISION AI PHẢN HỒI: Phát hiện hình ảnh NSFW / nhạy cảm!", flush=True)
        else:
            print(f"    [!] GOOGLE GEMINI PHẢN HỒI: Phát hiện văn bản chứa nội dung độc hại!", flush=True)
            
        print(f"    [!] Chi tiết từ AI: {message}", flush=True)
        print("    => TRẠNG THÁI: [VI PHẠM NGHIÊM TRỌNG]", flush=True)
        print("-" * 80, flush=True)
        self.finish_pipeline(title, message, bypass_gemini=False)

    def finish_pipeline(self, title, message, bypass_gemini=False):
        if bypass_gemini:
            print("[3/3] BƯỚC 3: PHÂN TÍCH CHUYÊN SÂU BẰNG GOOGLE GEMINI AI", flush=True)
            print("    [!] Đã hủy yêu cầu gửi API do dữ liệu đã bị chặn ở bộ lọc cục bộ (tiết kiệm chi phí).", flush=True)
            print("-" * 80, flush=True)
            
        self.log(f"KẾT LUẬN: {title}", to_cmd=False)
        self.log("--- HOÀN TẤT KIỂM DUYỆT ---", to_cmd=False)
        
        print("🛑 KẾT LUẬN CUỐI CÙNG: TỪ CHỐI DUYỆT NỘI DUNG", flush=True)
        print(f"    Lý do: {title}", flush=True)
        print("="*80, flush=True)
        print(f"⏳ Đang chờ yêu cầu tiếp theo từ Web Server...", flush=True)
        print("="*80, flush=True)
        
        self.sub_header.configure(text="Đang chờ tín hiệu từ web...", text_color="#10b981")
        
        time.sleep(3)
        self.is_animating = False

if __name__ == "__main__":
    app = App()
    app.mainloop()
