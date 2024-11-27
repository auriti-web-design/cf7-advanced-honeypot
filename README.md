# CF7 Advanced Honeypot System Documentation

## Overview

CF7 Advanced Honeypot System is a sophisticated anti-spam solution for Contact Form 7, designed to protect WordPress forms from automated submissions and spam attacks. The plugin implements an intelligent honeypot system with dynamic question generation and comprehensive tracking capabilities.

### Table of Contents

1\. [Features](features)

2\. [Installation](installation)

3\. [Technical Architecture](technical-architecture)

4\. [Usage](usage)

5\. [Security Measures](security-measures)

6\. [Statistics & Monitoring](statistics--monitoring)

7\. [Database Structure](database-structure)

8\. [Internationalization](internationalization)

9\. [Performance Optimization](performance-optimization)

10\. [API Reference](api-reference)

## Features

- Dynamic Honeypot Fields: Automatically generates and injects hidden fields with randomized questions

- Intelligent Spam Detection: Multi-layer verification system for form submissions

- Real-time Statistics: Comprehensive dashboard for monitoring spam attempts

- IP Tracking: Advanced IP detection and logging system

- Form-specific Monitoring: Track spam attempts across different Contact Form 7 forms

- Risk Assessment: Automatic classification of threats (Low, Medium, High)

- Log Management: Flexible log retention and cleanup options

- Responsive Design: Fully responsive administrative interface

- Internationalization Ready: Complete support for multiple languages

## Installation

### Requirements

- WordPress 5.0 or higher

- PHP 7.4 or higher

- Contact Form 7 plugin

- MySQL 5.6 or higher

## Installation Steps

1\. Upload the plugin to `/wp-content/plugins/`

2\. Activate the plugin through WordPress admin

3\. Navigate to "CF7 Honeypot" in the admin menu

4\. Configure settings if needed (default configuration works out of the box)

## Technical Architecture

### Core Components

1\. Main Plugin Class (`CF7_Advanced_Honeypot`):

   - Singleton pattern implementation

   - Manages plugin lifecycle

   - Handles hooks and filters

2\. Database Layer:

   - Two custom tables: `cf7_honeypot_questions` and `cf7_honeypot_stats`

   - Optimized queries with prepared statements

   - Automatic database updates handling

3\. Frontend Integration:

   - Dynamic CSS generation

   - Accessible honeypot fields

   - Progressive enhancement

4\. Admin Interface:

   - Real-time statistics dashboard

   - Log management system

   - Risk assessment visualization

##  Key Files Structure

```plaintext
cf7-advanced-honeypot/
├── assets/
│   ├── css/
│   │   └── admin-style.css
│   └── js/
├── languages/
├── templates/
│   └── stats-page.php
├── README.md
└── cf7-advanced-honeypot.php
```

## Usage

### Basic Implementation

The plugin works automatically after activation. No additional configuration is required for basic functionality.

## Advanced Configuration
```php

// Example: Customizing risk levels

add_filter('cf7_honeypot_risk_levels', function(levels) {

    return [

        'high' => 5,  // More than 5 attempts

        'medium' => 2 // More than 2 attempts

    ];

});
```

## Statistics Dashboard

Access the statistics dashboard via WordPress admin menu:

1\. Navigate to "CF7 Honeypot"

2\. View real-time statistics

3\. Analyze spam attempts

4\. Manage logs

## Security Measures

### Honeypot Implementation

- Dynamic field generation

- Randomized field names

- CSS-based field hiding

- Accessibility considerations

### Data Protection

- Prepared SQL statements

- Nonce verification

- Capability checks

- XSS prevention

- Input sanitization

### IP Detection

- Multiple header checking

- Proxy detection

- IPv6 support

- Local IP handling

## Statistics & Monitoring

### Available Metrics

- Total spam attempts

- 24-hour statistics

- 7-day trends

- 30-day analysis

- Per-form statistics

- IP-based analytics

## Risk Assessment System

```
Low Risk: 1-2 attempts

Medium Risk: 3-5 attempts

High Risk: 6+ attempts
```

## Database Structure

### Questions Table

```sql
CREATE TABLE `{prefix}cf7_honeypot_questions` (

    `id` mediumint(9) NOT NULL AUTO_INCREMENT,

    `question` text NOT NULL,

    `field_id` varchar(50) NOT NULL,

    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id)

);
```

### Statistics Table

```sql
CREATE TABLE `{prefix}cf7_honeypot_stats` (

    `id` mediumint(9) NOT NULL AUTO_INCREMENT,

    `form_id` bigint(20) NOT NULL,

    `honeypot_triggered` tinyint(1) NOT NULL DEFAULT 0,

    `ip_address` varchar(45),

    `email` varchar(255),

    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id)

);
```

## Internationalization

### Translation Support

- Full gettext integration

- RTL support

- Translation-ready strings

- Custom text domain

### Available Translations

- English (default)

- Italian

- [Community translations welcome]

## Performance Optimization

### Caching

- Dynamic CSS caching

- Database query optimization

- Minimal frontend impact

- Efficient log management

### Resource Usage

- Lightweight implementation

- Optimized database queries

- Minimal JavaScript usage

- Efficient CSS delivery

## API Reference

### Filters

```
// Customize risk levels

cf7_honeypot_risk_levels

// Modify cleanup intervals

cf7_honeypot_cleanup_intervals

// Adjust honeypot questions

cf7_honeypot_questions
```

## Actions

```
// Triggered on spam detection

do_action('cf7_honeypot_spam_detected', form_id, ip_address);

// Triggered on log cleanup

do_action('cf7_honeypot_logs_cleaned', period);
```

## Contributing

We welcome contributions! Please follow these steps:

1\. Fork the repository

2\. Create a feature branch

3\. Commit your changes

4\. Push to the branch

5\. Create a Pull Request

## License

This project is licensed under the GPL v2 or later.

## Support

- GitHub Issues: [https://github.com/auriti-web-design/cf7-advanced-honeypot/issues]

- Documentation: [https://github.com/auriti-web-design/cf7-advanced-honeypot/blob/master/README.md]

---

Last Updated: 27/11/2024

Version: 1.0.0

Author: Juan Camilo Auriti