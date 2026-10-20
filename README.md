# Tripura RailExpress
### Web-Based Railway Ticket Booking & Reservation System

---

## Project Overview
**Tripura RailExpress** is a web-based railway ticket reservation application designed for trains operating across Tripura routes. It features dynamic station route stops, automatic stage fare calculations, operating day schedules, travel class selection, ticket booking, simulated online payment processing, 10-digit PNR receipts, ticket cancellations, past journey completion tracking, registered user management, and an administrative control panel.

---

## System Features
- **Train Search Engine:** Search trains between stations with automatic direction validation.
- **Route Stops & Stage Fares:** Multi-stop train schedules with dynamic segment-based fare calculation.
- **5 Travel Classes:** Class quota limits for General (`GN`), Second Seating (`2S`), Sleeper (`SL`), AC Chair Car (`CC`), and AC 3-Tier (`3AC`) with price multipliers.
- **Operating Days Validation:** Schedule availability (`Mon-Sun`) validated dynamically against search dates.
- **Animated SVG CAPTCHA:** Two-layer horizontally sliced & opposite-shifted SVG keyframe animation for bot protection.
- **Simulated Payment Gateway:** 3-Tab payment interface (UPI/QR, Card, NetBanking) with transactional row locking (`FOR UPDATE`).
- **PNR Receipts & E-Tickets:** 10-Digit PNR lookup tracker and printable e-ticket receipt UI.
- **Past Journey Expiration:** Automatic completion status badge (`Completed`) and closed cancellation window for past dates (`journey_date < Today`).
- **Ticket Cancellation Engine:** Cancellation handler with dynamic seat quota restoration.
- **Admin Control Panel:** Manage train schedules, class capacities, intermediate station stops, and route fares.
- **Admin Reservation Tabs:** Status filter tabs (`All Reservations`, `Confirmed (Upcoming)`, `Completed (Past)`, `Pending Only`, `Cancelled Only`).
- **Registered Users Directory:** Account overview page detailing passenger account metrics and booking counts.

---

## Folder Structure
```
railway_booking_system/
├── config/
│   └── db_connect.php       # Database connection & running_days helper
├── database/
│   ├── railway_db.sql       # MySQL schema and seeded Tripura trains
│   └── setup.php            # Automated database installer script
├── admin/
│   ├── dashboard.php        # Admin panel statistics overview
│   ├── add_train.php        # Add train schedule form
│   ├── edit_train.php       # Unified train header, class quotas & stops editor
│   ├── confirm_delete_train.php # Train deletion confirmation warning
│   ├── delete_train.php     # Soft-delete train & booking cancellation handler
│   ├── all_bookings.php     # View all system reservations with status filters
│   ├── users_list.php       # Registered passenger accounts directory
│   └── booking_details.php  # Passenger manifest inspector & admin cancel
├── users/
│   ├── my_bookings.php      # User booking history & status indicators
│   ├── view_ticket.php      # Printable e-ticket receipt
│   ├── confirm_cancel.php   # Cancellation confirmation screen
│   └── cancel_ticket.php    # Ticket cancellation transaction handler
├── payment/
│   ├── checkout.php         # Payment gateway selection UI
│   └── process_payment.php  # Transaction ID generator & seat quota commit
├── includes/
│   ├── header.php           # Navigation bar & brand logo
│   └── footer.php           # Page footer
├── css/
│   └── style.css            # Custom responsive stylesheet
├── book.php                 # Ticket booking & passenger details form
├── captcha.php              # Animated two-layer sliced SVG CAPTCHA generator
├── index.php                # Homepage, train search & PNR tracker
├── login.php                # Login form & security CAPTCHA
├── register.php             # User registration form
├── logout.php               # Logout session destruction
└── README.md                # Open-source project documentation
```

---

## Installation & Quick Start Guide
1. Copy the `railway_booking_system` directory to your local web server root (e.g. `C:\xampp\htdocs\`).
2. Open XAMPP Control Panel and start **Apache** and **MySQL**.
3. Open your web browser and navigate to:
   `http://localhost/railway_booking_system/database/setup.php`  
   *(This will automatically create database tables and seed pre-configured train schedules).*
4. Access the web application at:
   `http://localhost/railway_booking_system/`

---

## Demo Test Credentials
- **Admin Account:** Username `admin` | Password `admin123`
- **User 1 Account:** Username `user1` | Password `user123`
- **User 2 Account:** Username `user2` | Password `user123`
- **User 3 Account:** Username `user3` | Password `user123`
