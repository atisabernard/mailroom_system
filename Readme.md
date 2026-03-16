# Mailroom System

A lightweight PHP + MySQL web application for managing incoming **documents**, **parcels**, and **newspapers** in an organization.

## Features

- **Dashboard** with summary cards and recent activity feed.
- **Document tracking** for recording received documents.
- **Parcel workflow**:
  - Register received parcels.
  - Auto-generate tracking IDs.
  - Record parcel pickup details.
  - View pending vs picked-up status.
- **Newspaper management**:
  - Manage newspaper categories.
  - Register daily newspaper issues.
  - Track available copies.
  - Record newspaper distribution.
- Simple UI built with **Tailwind CSS** and **Font Awesome**.

## Project Structure

- `index.php` - Main dashboard.
- `documents.php` - Document receiving and listing.
- `parcels.php` - Parcel receiving, pickup, and status.
- `list.php` - Newspaper and category/distribution management.
- `document_type.php` - Document type management.
- `newspaper_categories.php` - Newspaper category management.
- `print.php` - Printable views/reports.
- `config/db.php` - Database connection configuration.
- `header.php`, `sidebar.php` - Shared layout components.

## Requirements

- PHP 8.0+
- MySQL / MariaDB
- Web server (Apache/Nginx) with PHP support

## Setup

1. **Clone or copy the project** into your web root.
2. **Create a database** (example: `mailroom_system`).
3. **Import your SQL schema** containing the required tables.
4. **Configure DB credentials** in `config/db.php`.
5. Start your web server and open the app in your browser.

## Database Notes

The application references these core tables:

- `documents`
- `parcels_received`
- `parcels_pickup`
- `newspapers`
- `newspaper_categories`
- `distribution`

You may also have additional support tables depending on your local schema.

## Security & Production Notes

- Move DB credentials to environment variables for production deployments.
- Ensure proper input validation and escaping throughout all endpoints.
- Disable verbose error output in production (`display_errors=0`).

## Quick Start (Local)

If you want to test quickly with PHP's built-in server:

```bash
php -S localhost:8000
```

Then visit:

```text
http://localhost:8000
```

## License

Add your preferred license here (e.g., MIT) if this project will be distributed.
