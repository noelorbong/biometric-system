<!-- <p align="center">
		<img src="https://github.com/noelorbong/biometric-system/tree/main/public/images/logo/banner_white_mode.png" width="400" alt="Biometric Banner">
	
</p> -->

## Biometric Attendance System

Biometric Attendance System is a Laravel + Vue application for machine connectivity, attendance synchronization, user biometrics, and printable attendance reporting.

It is designed to support day-to-day HR attendance operations with role-based access and machine-to-system data flow.

## Device Compatibility

- Compatible with Granding biometric devices

## Core Features

- Machine management (connectivity test, device status, per-machine controls)
- Attendance synchronization from biometric machines
- Auto-download controls with interval settings and daemon/web fallback support
- Push users to machine with fingerprint template support
- Clear attendance logs per machine
- User management with profile, contacts, addresses, and biometric info
- Biometric attendance view with month/year filtering and print support
- Bulk biometric report generation with filters and printable outputs
- Dashboard experience for both Super Admin and User roles

## Roles and Access

- Role `1` (Super Admin): full access to all modules
- Role `0` (User): access limited to own dashboard, profile, and biometric data

Backend and frontend are both role-guarded to prevent unauthorized access.

## Tech Stack

- Backend: Laravel, Sanctum authentication
- Frontend: Vue 3, Pinia, Vue Router, Tailwind CSS
- Build tools: Vite
- Database: MySQL-compatible schema

## Getting Started

### 1. Install Dependencies

```bash
composer install
npm install
```

### 2. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

Update your `.env` database and app settings.

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Start Development Servers

```bash
php artisan serve
npm run dev
```

## Useful Commands

```bash
php artisan storage:link
php artisan test
php artisan optimize:clear
```

## Project Notes

- Main app routes are under `/main/...`
- API routes are protected with `auth:sanctum`
- Machine and reporting features are managed through dedicated controllers and Pinia stores

## Contributors

- Engr. Adrian U. Gadin - Director, Information Tech. Center
- Noel S. Orbong Jr - Information System Analyst II
