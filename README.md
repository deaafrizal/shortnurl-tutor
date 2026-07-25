# DEASHORTN - URL Shortener Tutorial

A simple PHP URL shortener application built for educational purposes to demonstrate security best practices.

## ⚠️ Educational Disclaimer

**This is a tutorial application for learning PHP security concepts.** It is **NOT recommended for production use** without significant additional security enhancements, including:

- User authentication and authorization system
- Advanced rate limiting and DDoS protection
- HTTPS enforcement
- Security headers (CSP, X-Frame-Options, etc.)
- Logging and monitoring
- Regular security audits
- Input sanitization enhancements

## Features

✅ Create short URLs from long URLs
✅ Track click counts
✅ View all shortened URLs
✅ Delete your shortened URLs
✅ CSRF protection
✅ XSS protection
✅ SQL injection protection (prepared statements)
✅ Rate limiting (30 URLs per hour per IP)
✅ IP anonymization for privacy
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
- Tokens generated securely using `random_bytes()`
- Tokens validated with `hash_equals()` for timing-safe comparison

### 3. XSS Protection
- All user input escaped with `htmlspecialchars()`
- Output properly escaped in templates

### 4. SQL Injection Prevention
- All database queries use prepared statements
- User input never directly interpolated into SQL

### 5. Rate Limiting
- Maximum 30 URLs per IP per hour
- Maximum 50 total URLs per IP lifetime
- Prevents abuse and resource exhaustion

### 6. IP Anonymization
- User IPs displayed in "Active Users" list are anonymized
- IPv4: Last octet masked (e.g., 192.168.1.xxx)
- IPv6: Last 64 bits masked (e.g., 2001:db8:xxxx:xxxx:xxxx:xxxx:xxxx:xxxx)
- Protects user privacy while maintaining statistics

### 7. Proxy Support
- Detects and uses `X-Forwarded-For` header
- Supports Cloudflare `CF-Connecting-IP` header
- Proper IP detection behind reverse proxies

## File Structure

```
shorturl-tutor/
├── config/
│   └── database.php          # Database configuration and connection
├── public/
│   └── index.php             # Main application entry point
├── src/
│   ├── Csrf.php              # CSRF token handling
│   ├── IpAnonymizer.php      # IP anonymization utilities
│   ├── NotFoundException.php  # Custom exception
│   ├── RateLimiter.php       # Rate limiting logic
│   ├── Redirect.php          # URL redirect logic
│   └── Shorten.php           # URL shortening logic
├── .env.example              # Environment variables template
├── .gitignore                # Git ignore rules
└── README.md                 # This file
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

- **Hourly Limit:** 30 URLs per IP address per hour
- **Lifetime Limit:** 50 total URLs per IP address
- **Error Message:** Provides remaining quota information

These limits can be adjusted in `src/RateLimiter.php` and `src/Shorten.php`.

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

This tutorial demonstrates:

1. ✅ Secure credential management with environment variables
2. ✅ CSRF protection implementation
3. ✅ XSS prevention through output escaping
4. ✅ SQL injection prevention with prepared statements
5. ✅ Rate limiting and abuse prevention
6. ✅ User privacy protection (IP anonymization)
7. ✅ Proper error handling
8. ✅ Database connection best practices

## Common Issues

### "FATAL ERROR: .env file not found"
- **Solution:** Run `cp .env.example .env` and configure your database credentials

### "Missing required database environment variables"
- **Solution:** Ensure all `DB_*` variables are set in `.env` file

### "Database connection failed"
- **Solution:** Check your database credentials and ensure MySQL is running

### "Rate limit exceeded"
- **Solution:** You've created too many URLs in the last hour. Try again later.

## Security Recommendations for Production

If you choose to adapt this for production, implement:

```
☐ User authentication (login/register)
☐ User-based URL quotas instead of IP-based
☐ HTTPS/SSL enforcement
☐ Security headers (CSP, X-Frame-Options, etc.)
☐ Logging and monitoring
☐ Backup and disaster recovery
☐ Database encryption
☐ Web Application Firewall (WAF)
☐ Regular security audits
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
