# DEASHORTN - URL Shortener

[![Deploy ShortnURL](https://github.com/deaafrizal/shortnurl-tutor/actions/workflows/deploy.yml/badge.svg)](https://github.com/deaafrizal/shortnurl-tutor/actions/workflows/deploy.yml)

A production-ready PHP URL shortener with a strong security focus.

## Features

✅ Create short URLs from long URLs
✅ Track click counts
✅ View all shortened URLs
✅ Delete your shortened URLs
✅ CSRF protection (with token rotation per request)
✅ XSS protection (all output escaped)
✅ SQL injection protection (prepared statements, native PDO)
✅ Rate limiting (30 URLs per hour per IP)
✅ IP anonymization for privacy
✅ IP spoofing protection (Cloudflare validation only)
✅ Secure session cookies (HttpOnly + Secure + SameSite=Strict)
✅ Content Security Policy (CSP) headers
✅ Security headers (X-Frame-Options, X-Content-Type-Options, HSTS)
✅ Automated CI/CD via GitHub Actions
✅ Database credential validation

## Requirements

- PHP 8.0+
- MySQL/MariaDB 5.7+
- Web server (Apache, Nginx, etc.)

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/deaafrizal/shortnurl-tutor.git
cd shortnurl-tutor
```

### 2. Create `.env` file

```bash
cp .env.example .env
```

Edit `.env` with your database credentials:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shortnurl
DB_USERNAME=shortnurl
DB_PASSWORD=your_strong_password
BASE_URL=http://localhost:8000
```

**Important:** All database variables (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) are **REQUIRED**. The application will fail to start if any are missing.

### 3. Create database and tables

```sql
CREATE DATABASE shortnurl;
USE shortnurl;

CREATE TABLE urls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_url TEXT NOT NULL,
    short_code VARCHAR(10) UNIQUE NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    click_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_short_code (short_code),
    INDEX idx_ip_address (ip_address)
);
```

### 4. Set file permissions

```bash
chmod 600 .env
chmod 755 public/
```

### 5. Run the application

**Using PHP built-in server:**

```bash
cd public
php -S localhost:8000
```

Then visit: `http://localhost:8000`

**Using Apache/Nginx:**

Point your web server's document root to the `public/` directory.

## Security Features

### 1. Database Credential Validation
- Application fails loudly if `.env` file is missing
- All required database credentials must be explicitly set
- No hardcoded fallback credentials

### 2. CSRF Protection
- All POST requests validated with CSRF tokens
- Tokens generated securely using `random_bytes()` (64 chars hex)
- Tokens validated with `hash_equals()` for timing-safe comparison
- **Token rotation** after every successful POST (shorten/delete)
- Prevents replay attacks if token is intercepted

### 3. XSS Protection
- All user input escaped with `htmlspecialchars()`
- Output properly escaped in templates

### 4. SQL Injection Prevention
- All database queries use prepared statements
- PDO `EMULATE_PREPARES=false` — forces real native prepared statements
- User input never directly interpolated into SQL

### 5. Rate Limiting
- Maximum 30 URLs per IP per hour (sliding window)
- Maximum 50 total URLs per IP lifetime
- Prevents abuse and resource exhaustion

### 6. IP Anonymization
- User IPs displayed in "Active Users" list are fully anonymized
- IPv4: Last octet masked (e.g., 192.168.1.xxx)
- IPv6: Last 64 bits masked (e.g., 2001:db8:xxxx:xxxx:xxxx:xxxx:xxxx:xxxx)
- Raw IPs stored in database, only anonymized values shown publicly
- Protects user privacy while maintaining statistics

### 7. IP Spoofing Protection
- **Only** trusts `CF-Connecting-IP` header if request originates from Cloudflare IP ranges
- Cloudflare IP list (15 CIDR ranges) verified before trusting header
- **Ignores** raw `X-Forwarded-For` headers from untrusted proxies
- Falls back to `REMOTE_ADDR` (actual TCP connection IP)
- Prevents IP-based authorization bypass attacks

### 8. Secure Session Configuration
- **HttpOnly**: Cookies inaccessible to JavaScript
- **Secure**: Cookies only sent over HTTPS
- **SameSite=Strict**: Cookies not sent on cross-site requests
- **Lifetime=0**: Session expires when browser closes
- Prevents session hijacking and CSRF via cookie

### 9. Content Security Policy (CSP)
- `default-src 'self'` — blocks all unauthorized resource loading
- `script-src` limited to `self` + `cdn.tailwindcss.com`
- `form-action 'self'` — forms can only submit to own origin
- `frame-ancestors 'none'` — prevents clickjacking
- Prevents XSS exploitation even if a vulnerability is found

### 10. Additional HTTP Security Headers
| Header | Value | Purpose |
|---|---|---|
| `X-Frame-Options` | `SAMEORIGIN` | Anti-clickjacking |
| `X-Content-Type-Options` | `nosniff` | Prevent MIME sniffing |
| `X-XSS-Protection` | `1; mode=block` | Legacy XSS filter |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Referrer leakage control |

## CI/CD Pipeline

Every push to `main` branch triggers an automated deployment via GitHub Actions:

```yaml
1. Checkout code
2. Setup PHP 8.3 with required extensions
3. Validate composer.json
4. Cache Composer dependencies
5. Install dependencies
6. PHP syntax check (all source files)
7. Run PHPUnit tests (17+ test cases)
8. Deploy to production VPS via SSH
```

**Secrets required in GitHub repository:**

| Secret | Description |
|---|---|
| `SSH_HOST` | VPS IP address |
| `SSH_USER` | SSH user (root) |
| `SSH_PRIVATE_KEY` | Ed25519 deploy key |
| `DEPLOY_PATH` | Absolute path to project on VPS |

## File Structure

```
shorturl-tutor/
├── .github/
│   └── workflows/
│       └── deploy.yml         # CI/CD deployment workflow
├── config/
│   └── database.php           # Database configuration and connection
├── public/
│   └── index.php              # Main application entry point (front controller)
├── src/
│   ├── Csrf.php               # CSRF token handling + session config
│   ├── IpAnonymizer.php       # IP anonymization utilities
│   ├── NotFoundException.php  # Custom 404 exception
│   ├── RateLimiter.php        # Rate limiting logic
│   ├── Redirect.php           # URL redirect logic
│   └── Shorten.php            # URL shortening + IP validation
├── tests/
│   ├── ShortenTest.php        # Unit tests for Shorten
│   └── RedirectTest.php       # Unit tests for Redirect
├── .env.example               # Environment variables template
├── .gitignore                 # Git ignore rules
├── composer.json              # PHP dependencies
└── README.md                  # This file
```

## Usage

### Creating a Short URL

1. Visit the application homepage
2. Enter a long URL in the input field
3. Click "Shorten"
4. Copy the generated short URL

### Viewing Your URLs

1. Click "My URLs" in the navigation
2. All URLs created from your IP are displayed
3. See click counts and creation timestamps

### Deleting a Short URL

1. Go to "My URLs"
2. Find the URL you want to delete
3. Click the "Delete" button
4. Confirm the deletion

## Rate Limits

- **Hourly Limit:** 30 URLs per IP address per hour (configurable in `src/RateLimiter.php`)
- **Lifetime Limit:** 50 total URLs per IP address (configurable in `src/Shorten.php`)
- **Nginx Rate Limit:** 5 requests/second per IP across all endpoints
- **SSH Rate Limit:** 4 connections per 30 seconds per IP (via iptables)
- **Error Message:** Provides remaining quota information

## Environment Variables

```dotenv
# Application
APP_NAME              Application name (default: DEASHORTN)
APP_ENV               Environment mode (default: production)
APP_DEBUG             Debug mode (default: false)

# Database (ALL REQUIRED)
DB_HOST               Database host (no default)
DB_PORT               Database port (no default)
DB_DATABASE           Database name (no default)
DB_USERNAME           Database user (no default)
DB_PASSWORD           Database password (no default)

# Application
BASE_URL              Base URL for short links (default: http://localhost)
```

## Learning Goals

This project demonstrates:

1. ✅ Secure credential management with environment variables
2. ✅ CSRF protection with token rotation
3. ✅ XSS prevention through output escaping
4. ✅ SQL injection prevention with prepared statements
5. ✅ Rate limiting (hourly + lifetime + nginx + iptables)
6. ✅ User privacy protection (IP anonymization)
7. ✅ IP spoofing prevention (Cloudflare IP validation)
8. ✅ Secure session configuration (HttpOnly + Secure + SameSite)
9. ✅ Content Security Policy (CSP) headers
10. ✅ Automated CI/CD deployment via GitHub Actions
11. ✅ Server-level SSH hardening (fail2ban, key-only auth)
12. ✅ Proper error handling

## Common Issues

### "FATAL ERROR: .env file not found"
- **Solution:** Run `cp .env.example .env` and configure your database credentials

### "Missing required database environment variables"
- **Solution:** Ensure all `DB_*` variables are set in `.env` file

### "Database connection failed"
- **Solution:** Check your database credentials and ensure MySQL is running

### "Rate limit exceeded"
- **Solution:** You've created too many URLs in the last hour. Try again later.

## Additional Recommendations

Items already implemented in this project are marked ✅.

```
✅ HTTPS/SSL enforcement (nginx redirect)
✅ Security headers (CSP, X-Frame-Options, HSTS, etc.)
✅ CSRF protection with token rotation
✅ Session hardening (HttpOnly, Secure, SameSite)
✅ Rate limiting (application + nginx + iptables)
✅ Fail2ban integration
✅ IP anonymization (privacy)
☐ User authentication (login/register)
☐ User-based URL quotas instead of IP-based
☐ Audit logging (file integrity monitoring)
☐ Backup and disaster recovery
☐ Database encryption at rest
☐ Web Application Firewall (WAF)
☐ Subresource Integrity (SRI) for CDN scripts
☐ Penetration testing
```

## License

MIT License - See LICENSE file for details

## Contributing

This is an educational project. Contributions and feedback are welcome!

## Support

For issues or questions, please open a GitHub issue.

## Author

Created by [@deaafrizal](https://github.com/deaafrizal) as a learning resource.
