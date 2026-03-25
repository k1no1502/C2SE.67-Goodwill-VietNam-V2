from PIL import Image, ImageDraw, ImageFont
import os

uploads_dir = r'c:\xampp\htdocs\GW_VN Ver Final\uploads'

# Create carousel-1.jpg
img1 = Image.new('RGB', (500, 800), color=(102, 126, 234))
draw1 = ImageDraw.Draw(img1)
try:
    font = ImageFont.truetype("C:\\Windows\\Fonts\\arial.ttf", 36)
    font_small = ImageFont.truetype("C:\\Windows\\Fonts\\arial.ttf", 24)
except:
    font = ImageFont.load_default()
    font_small = ImageFont.load_default()

# Slide 1
draw1.text((40, 320), "Cung xay dung cong dong", fill=(255, 255, 255), font=font)
draw1.text((60, 380), "tot lanh", fill=(255, 255, 255), font=font)
img1.save(os.path.join(uploads_dir, 'carousel-1.jpg'))
print("✓ carousel-1.jpg created")

# Slide 2
img2 = Image.new('RGB', (500, 800), color=(166, 124, 180))
draw2 = ImageDraw.Draw(img2)
draw2.text((30, 320), "Chung tay giup do", fill=(255, 255, 255), font=font)
draw2.text((40, 380), "nhung nguoi kho khan", fill=(255, 255, 255), font=font)
img2.save(os.path.join(uploads_dir, 'carousel-2.jpg'))
print("✓ carousel-2.jpg created")

# Slide 3
img3 = Image.new('RGB', (500, 800), color=(150, 90, 180))
draw3 = ImageDraw.Draw(img3)
draw3.text((50, 320), "Hanh dong nho", fill=(255, 255, 255), font=font)
draw3.text((80, 380), "Anh huong lon", fill=(255, 255, 255), font=font)
img3.save(os.path.join(uploads_dir, 'carousel-3.jpg'))
print("✓ carousel-3.jpg created")

print("\nAll carousel images created successfully!")
