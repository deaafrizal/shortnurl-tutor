# 🤖 AI Coding Agent Guidelines

Dokumen ini berisi standar kerja & aturan wajib bagi AI Agent di repository ini.

---

## 1. Project Context & Environment
- **Environment:** Linux / VPS (Ubuntu 24.04, Nginx, PHP 8.x, MySQL).
- **Project Type:** Web Application (PHP Native / Laravel).
- Gunakan environment variables (`.env`) untuk data sensitif. Jangan pernah simpan kredensial/API keys langsung di dalam kode.

---

## 2. Code Quality & Security
- Tulis kode yang modular, mudah dibaca, dan aman dari kerentanan umum (SQL Injection & XSS).
- Sebelum menyelesaikan tugas, pastikan kode telah divalidasi dan bebas dari kesalahan sintaks (`php -l` atau unit test jika ada).

---

## 3. Git Workflow & Mandatory CI/CD Trigger (WAJIB)
1. **Granular Commit:** Lakukan `git commit` untuk setiap 1 tugas/fitur kecil yang selesai dikerjakan. Gunakan format konvensi pesan commit (contoh: `feat: ...` atau `fix: ...`).
2. **Auto Push:** Setelah komit berhasil dan dipastikan bebas error, kamu **WAJIB** menjalankan perintah:
   `git push origin main`
   
   > ⚠️ **Catatan Penting:** Perintah `git push` ini adalah pemicu (*trigger*) otomatis untuk pipeline CI/CD (GitHub Actions) agar perubahan ter-deploy langsung ke VPS.

---

## 4. Restrictions (Yang Dilarang)
- ❌ Dilarang melakukan `git push` jika kodingan masih bermasalah/error.
- ❌ Dilarang menjalankan perintah terminal berskala destruktif (`rm -rf /`, `DROP DATABASE`, dll) tanpa persetujuan[cite: 1].
- ❌ Dilarang mengubah struktur folder utama aplikasi tanpa instruksi spesifik[cite: 1].
