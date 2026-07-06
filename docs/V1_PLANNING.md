# V1 Planning Document

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

## Module 1: Authentication & Users DB Structure

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
