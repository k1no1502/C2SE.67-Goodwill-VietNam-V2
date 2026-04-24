# -*- coding: utf-8 -*-
"""
Test Account Manager - Goodwill Vietnam Platform
Quan ly test account va tu dong dang nhap
"""

import tkinter as tk
from tkinter import ttk, messagebox
import threading
import os
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options
from selenium.common.exceptions import WebDriverException
import time

# Configuration
WEBSITE_URL = "http://localhost/"
LOGIN_URL = f"{WEBSITE_URL}login.php"
PASSWORD = "123456"

# Test accounts
TEST_ACCOUNTS = [f"test{i}" for i in range(1, 11)]

# Local Chrome profile storage for each test account
PROFILES_BASE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "chrome_profiles")


class TestAccountManager:
    def __init__(self, root):
        self.root = root
        self.root.title("Test Account Manager - Goodwill Vietnam")
        self.root.geometry("600x700")
        self.root.resizable(False, False)
        
        # Set theme colors
        self.bg_color = "#f0f0f0"
        self.primary_color = "#28a745"
        self.secondary_color = "#17a2b8"
        self.root.configure(bg=self.bg_color)
        
        # Current logged-in account
        self.current_account = tk.StringVar(value="Chưa đăng nhập")
        self.driver = None
        
        # Create UI
        self.create_widgets()
    
    def create_widgets(self):
        """Create the GUI elements"""
        
        # Header
        header_frame = tk.Frame(self.root, bg=self.primary_color, height=80)
        header_frame.pack(fill=tk.X)
        
        title_label = tk.Label(
            header_frame,
            text="Test Account Manager",
            font=("Arial", 18, "bold"),
            bg=self.primary_color,
            fg="white"
        )
        title_label.pack(pady=10)
        
        subtitle_label = tk.Label(
            header_frame,
            text="Quản lý tài khoản kiểm thử",
            font=("Arial", 10),
            bg=self.primary_color,
            fg="white"
        )
        subtitle_label.pack()
        
        # Current account display
        info_frame = tk.Frame(self.root, bg="white", relief=tk.RAISED, bd=1)
        info_frame.pack(fill=tk.X, padx=10, pady=10)
        
        info_label = tk.Label(
            info_frame,
            text="Tài khoản hiện tại:",
            font=("Arial", 10),
            bg="white"
        )
        info_label.pack(side=tk.LEFT, padx=10, pady=8)
        
        account_label = tk.Label(
            info_frame,
            textvariable=self.current_account,
            font=("Arial", 11, "bold"),
            bg="white",
            fg=self.primary_color
        )
        account_label.pack(side=tk.LEFT, padx=5, pady=8)
        
        # Buttons frame
        buttons_frame = tk.Frame(self.root, bg=self.bg_color)
        buttons_frame.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)
        
        # Create grid of buttons
        for i, account in enumerate(TEST_ACCOUNTS):
            row = i // 5
            col = i % 5
            
            btn = tk.Button(
                buttons_frame,
                text=account.upper(),
                width=8,
                height=2,
                font=("Arial", 11, "bold"),
                bg=self.secondary_color,
                fg="white",
                activebackground="#138496",
                activeforeground="white",
                relief=tk.RAISED,
                bd=2,
                cursor="hand2",
                command=lambda acc=account: self.login_account(acc)
            )
            btn.grid(row=row, column=col, padx=5, pady=5, sticky="nsew")
            
            # Configure grid weights for responsive layout
            buttons_frame.grid_rowconfigure(row, weight=1)
            buttons_frame.grid_columnconfigure(col, weight=1)
        
        # Bottom info frame
        bottom_frame = tk.Frame(self.root, bg="white", relief=tk.SUNKEN, bd=1)
        bottom_frame.pack(fill=tk.X, padx=10, pady=10)
        
        info_text = tk.Label(
            bottom_frame,
            text="💡 Nhấp vào tài khoản kiểm thử để đăng nhập tự động",
            font=("Arial", 9),
            bg="white",
            fg="#666"
        )
        info_text.pack(pady=8)
        
        # Status bar
        self.status_var = tk.StringVar(value="Sẵn sàng")
        status_label = tk.Label(
            self.root,
            textvariable=self.status_var,
            font=("Arial", 9),
            bg="#e0e0e0",
            relief=tk.SUNKEN,
            anchor=tk.W,
            padx=5,
            pady=3
        )
        status_label.pack(fill=tk.X, side=tk.BOTTOM)
    
    def login_account(self, account):
        """Login to a test account"""
        # Run login in a separate thread to avoid freezing
        thread = threading.Thread(target=self._perform_login, args=(account,))
        thread.daemon = True
        thread.start()
    
    def _perform_login(self, account):
        """Perform the actual login"""
        try:
            self.update_status(f"Đang đăng nhập vào {account}...")
            
            # Close previous driver if exists
            if self.driver:
                try:
                    self.driver.quit()
                except:
                    pass
            
            # Setup Chrome options
            chrome_options = Options()
            # chrome_options.add_argument("--headless")  # Comment this to see the browser
            chrome_options.add_argument("--no-sandbox")
            chrome_options.add_argument("--disable-dev-shm-usage")
            chrome_options.add_argument("--disable-gpu")
            chrome_options.add_argument("--no-first-run")
            chrome_options.add_argument("--no-default-browser-check")

            # Create/use dedicated Chrome profile for the selected test account
            account_profile_dir = os.path.join(PROFILES_BASE_DIR, account.lower())
            os.makedirs(account_profile_dir, exist_ok=True)
            chrome_options.add_argument(f"--user-data-dir={account_profile_dir}")

            self.update_status(f"Đang mở Chrome profile: {account}")
            
            # Initialize WebDriver via Selenium Manager (auto-resolve correct driver)
            self.driver = webdriver.Chrome(options=chrome_options)
            
            # Navigate to login page
            self.driver.get(LOGIN_URL)
            self.update_status("Đang nạp trang đăng nhập...")
            
            # Wait for email field and fill it
            email_field = WebDriverWait(self.driver, 10).until(
                EC.presence_of_element_located((By.NAME, "email"))
            )
            email_field.clear()
            email_field.send_keys(f"{account}@goodwillvietnam.com")
            self.update_status("Đã nhập email...")
            
            # Fill password field
            password_field = self.driver.find_element(By.NAME, "password")
            password_field.clear()
            password_field.send_keys(PASSWORD)
            self.update_status("Đã nhập mật khẩu...")
            
            # Click login button - find by CSS selector (submit button in form)
            time.sleep(0.5)
            login_buttons = self.driver.find_elements(By.CSS_SELECTOR, "button[type='submit'].btn-login")
            if not login_buttons:
                login_buttons = self.driver.find_elements(By.CSS_SELECTOR, "button[type='submit']")
            login_button = login_buttons[0]
            login_button.click()
            self.update_status("Đang xác minh...")
            
            # Wait for page to load after login
            time.sleep(3)
            
            # Check if login was successful
            if "login" not in self.driver.current_url.lower():
                self.current_account.set(f"✓ {account.upper()}")
                self.update_status(f"Đã đăng nhập {account} thành công!")
                messagebox.showinfo("Thành công", f"Đã đăng nhập vào {account} thành công!\nBrowser sẽ tiếp tục mở.")
            else:
                self.update_status(f"Lỗi: Không thể đăng nhập {account}")
                messagebox.showerror("Lỗi", f"Không thể đăng nhập vào {account}.\nVui lòng kiểm tra thông tin tài khoản.")
        
        except WebDriverException as e:
            error_msg = str(e)
            if "WinError 193" in error_msg:
                error_msg = (
                    "Driver không tương thích với Windows hiện tại. "
                    "Hãy cập nhật Chrome và chạy lại tool để Selenium Manager tải đúng driver."
                )
            self.update_status(f"Lỗi: {error_msg}")
            messagebox.showerror("Lỗi WebDriver", f"Có lỗi WebDriver xảy ra:\n{error_msg}")
            print(f"WebDriver Error: {e}")

        except Exception as e:
            error_msg = str(e)
            self.update_status(f"Lỗi: {error_msg}")
            messagebox.showerror("Lỗi đăng nhập", f"Có lỗi xảy ra:\n{error_msg}")
            print(f"Error: {e}")
    
    def update_status(self, message):
        """Update status bar"""
        self.status_var.set(message)
        self.root.update()
    
    def on_closing(self):
        """Handle window closing"""
        if self.driver:
            try:
                self.driver.quit()
            except:
                pass
        self.root.destroy()


def main():
    root = tk.Tk()
    app = TestAccountManager(root)
    root.protocol("WM_DELETE_WINDOW", app.on_closing)
    root.mainloop()


if __name__ == "__main__":
    main()
