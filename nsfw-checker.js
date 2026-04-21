// nsfw-checker.js
// Node.js Express service: nhận ảnh (URL hoặc file), gửi tới Qwen 3.5, trả về kết quả NSFW

const express = require('express');
const { OpenAI } = require('openai');
const multer = require('multer');
const fs = require('fs');
const path = require('path');
require('dotenv').config();

const app = express();
const upload = multer({ dest: 'uploads/' });
const PORT = process.env.PORT || 3001;

const client = new OpenAI({
  baseURL: 'https://router.huggingface.co/v1',
  apiKey: process.env.HF_TOKEN,
});

// Các từ khóa NSFW cần chặn
// Chỉ kiểm duyệt 2 yếu tố: không mặc áo hoặc không mặc quần
const REJECT_KEYWORDS = [
  'không mặc áo', 'cởi trần', 'shirtless', 'bare chest', 'no shirt', 'topless',
  'không mặc quần', 'pantless', 'no pants', 'bottomless'
];

// Helper: kiểm tra mô tả có chứa từ khóa từ chối không
function isNSFW(description) {
  const desc = description.toLowerCase();
  return REJECT_KEYWORDS.some(keyword => desc.includes(keyword));
}

app.use(express.json());

// Nhận ảnh qua URL
app.post('/check-url', async (req, res) => {
  const { imageUrl } = req.body;
  if (!imageUrl) return res.status(400).json({ error: 'Missing imageUrl' });
  try {
    const chatCompletion = await client.chat.completions.create({
      model: 'Qwen/Qwen3.5-35B-A3B:novita',
      messages: [
        {
          role: 'user',
          content: [
            { type: 'text', text: 'Describe this image in one sentence. Be explicit if it contains nudity, sex, porn, or adult content.' },
            { type: 'image_url', image_url: { url: imageUrl } },
          ],
        },
      ],
    });
    const desc = chatCompletion.choices[0].message.content || '';
    const nsfw = isNSFW(desc);
    let reason = '';
    if (nsfw) {
      if (desc.includes('không mặc áo') || desc.includes('cởi trần') || desc.includes('shirtless') || desc.includes('bare chest') || desc.includes('no shirt') || desc.includes('topless')) {
        reason = 'Ảnh bị từ chối vì phát hiện người trong ảnh không mặc áo.';
      } else if (desc.includes('không mặc quần') || desc.includes('pantless') || desc.includes('no pants') || desc.includes('bottomless')) {
        reason = 'Ảnh bị từ chối vì phát hiện người trong ảnh không mặc quần.';
      } else {
        reason = 'Ảnh bị từ chối vì không phù hợp quy định.';
      }
    }
    res.json({ nsfw, description: desc, reason });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Nhận ảnh upload file
app.post('/check-file', upload.single('image'), async (req, res) => {
  if (!req.file) return res.status(400).json({ error: 'Missing image file' });
  const filePath = path.resolve(req.file.path);
  try {
    // Đọc file và encode base64
    const imageData = fs.readFileSync(filePath, { encoding: 'base64' });
    const imageUrl = `data:image/${path.extname(filePath).slice(1)};base64,${imageData}`;
    const chatCompletion = await client.chat.completions.create({
      model: 'Qwen/Qwen3.5-35B-A3B:novita',
      messages: [
        {
          role: 'user',
          content: [
            { type: 'text', text: 'Describe this image in one sentence. Be explicit if it contains nudity, sex, porn, or adult content.' },
            { type: 'image_url', image_url: { url: imageUrl } },
          ],
        },
      ],
    });
    const desc = chatCompletion.choices[0].message.content || '';
    const nsfw = isNSFW(desc);
    res.json({ nsfw, description: desc });
  } catch (err) {
    res.status(500).json({ error: err.message });
  } finally {
    fs.unlinkSync(filePath); // Xóa file tạm
  }
});

app.listen(PORT, () => {
  console.log(`NSFW Checker service running on port ${PORT}`);
});
