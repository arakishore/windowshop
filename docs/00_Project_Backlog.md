# Project Backlog

## Project Overview

Laravel + Blade + REST API SaaS project for a hyperlocal marketplace, private shop app, and mobile POS platform.

## Database Architecture Decision

### Main Application Database

Database name: `webtree_commerce`

Purpose:

Stores all business and transactional data, including:

- shops
- products
- customers
- orders
- POS bills
- inventory
- subscriptions
- settings

### Separate Logs Database

Database name: `webtree_commerce_logs`

Purpose:

Stores heavy audit, activity, debug, and history data, including:

- user activity logs
- admin action logs
- shop action logs
- staff action logs
- API logs
- notification logs
- AI usage logs
- login/logout logs
- error/debug logs

### Architecture Rule

Main DB = business and transactional data.

Logs DB = audit, activity, debug, and history data.

## Current Focus: Authentication and Users

Planned areas to design next:

- users
- user types
- roles
- permissions
- shop owner mapping
- staff mapping
- customer login
- password reset
- mobile device tokens

## Pending POS Enhancements

- Add optional POS payment reference fields when needed:
  - UPI transaction/reference number for UPI payments
  - Terminal/Auth reference for card payments
  - Optional generic payment reference for manual reconciliation
- Keep these fields hidden for Cash unless a clear business need appears.
- Store references in existing order fields:
  - `payment_reference`
  - `upi_txn`
  - `terminal_id`
- Continue showing saved references on receipts and sales detail pages.
