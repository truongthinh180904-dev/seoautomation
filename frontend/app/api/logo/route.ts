import { NextResponse } from 'next/server';
import fs from 'fs';
import path from 'path';

// Đường dẫn ảnh logo do người dùng tải lên
const SOURCE_IMAGE_PATH =
  '/Users/truongthinh/.gemini/antigravity-cli/brain/ca300cd1-7fc8-4483-8e52-14cc15b4b9cb/.user_uploaded/uploaded_media_1794360038763.jpg';

export async function GET() {
  try {
    const publicDir = path.join(process.cwd(), 'public');
    const targetJpg = path.join(publicDir, 'logo.jpg');
    const targetPng = path.join(publicDir, 'logo.png');

    let imageBuffer: Buffer | null = null;

    if (fs.existsSync(SOURCE_IMAGE_PATH)) {
      imageBuffer = fs.readFileSync(SOURCE_IMAGE_PATH);
      // Tự động đồng bộ sang thư mục public/ để có thể truy cập qua /logo.jpg và /logo.png
      try {
        if (!fs.existsSync(publicDir)) {
          fs.mkdirSync(publicDir, { recursive: true });
        }
        fs.writeFileSync(targetJpg, imageBuffer);
        fs.writeFileSync(targetPng, imageBuffer);
      } catch (writeErr) {
        console.warn('Sync logo to public failed:', writeErr);
      }
    } else if (fs.existsSync(targetJpg)) {
      imageBuffer = fs.readFileSync(targetJpg);
    }

    if (!imageBuffer) {
      return new NextResponse('Logo not found', { status: 404 });
    }

    return new NextResponse(imageBuffer, {
      status: 200,
      headers: {
        'Content-Type': 'image/jpeg',
        'Cache-Control': 'public, max-age=31536000, immutable',
      },
    });
  } catch (error) {
    console.error('Error serving logo:', error);
    return new NextResponse('Internal error', { status: 500 });
  }
}
