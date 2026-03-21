<h1 align="center">SMS Dashboard</h1>

<p align="center">
  <a href="https://github.com/your-org/your-repo/actions"><img src="https://img.shields.io/github/actions/workflow/status/your-org/your-repo/phpunit.yml?branch=main" alt="CI Status"></a>
  <a href="https://packagist.org/packages/your-org/your-package"><img src="https://img.shields.io/packagist/v/your-org/your-package" alt="Packagist Version"></a>
  <a href="https://img.shields.io/badge/license-MIT-blue.svg"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="License"></a>
</p>

## About this project

SMS Dashboard is a Laravel-based school scheduling and communications system built to manage students, teachers, courses, classrooms, and automated SMS alerts. It provides centralized administration for academic schedules, bulk SMS reminders, and import/export operations.

Key capabilities:

- Schedule creation and timetable management for courses, teachers, rooms, and timeslots.
- Student and teacher administration, including imports from CSV/XLSX.
- Automated SMS notifications for schedule assignments and alerts.
- Classroom and semester management with conflict detection.
- Data exports (students, teachers, schedules) for reporting and backup.

## Features

- Clean MVC structure with Laravel Eloquent models and relationships.
- Role-based access controls (admins, schedulers, viewers).
- Fast search and filters for students, teachers, courses, semsters, rooms, and timeslots.
- Import utilities supporting bulk data ingestion via `app/Imports`.
- Export utilities via `app/Exports` in spreadsheet-friendly formats.

## Getting started

1. Clone repository:
   ```bash
   git clone https://github.com/your-org/your-repo.git
   cd your-repo
   ```
2. Install dependencies:
   ```bash
   composer install
   npm install
   ```
3. Create environment file and app key:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Configure database and SMS credentials in `.env` (e.g., Twilio, Nexmo).
5. Run migrations and seed initial data:
   ```bash
   php artisan migrate --seed
   ```
6. Start local server:
   ```bash
   php artisan serve
   npm run dev
   ```

## Usage

- Access the web UI at `http://127.0.0.1:8000`.
- Log in as an administrator, then configure departments, semesters, and classes.
- Use "Import" pages to bulk upload students, teachers, courses, rooms, and timeslots.
- Create a schedule and send SMS alerts from the schedule view.

## Contributing

1. Fork and create a feature branch.
2. Commit with meaningful messages.
3. Open a pull request describing the change.

## License

This project is licensed under the MIT License.

