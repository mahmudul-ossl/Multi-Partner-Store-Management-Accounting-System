# Multi-Partner Store Management & Accounting System

A production-ready **multi-partner store management, inventory, sales, accounting, investment, withdrawal, promotion, approval, and reporting system** built with Laravel.

The system is designed for businesses where multiple partners jointly operate a store, contribute investments, withdraw funds, spend money on promotions, manage inventory, process sales, and share business profits.

---

## Table of Contents

* [Overview](#overview)
* [Key Features](#key-features)
* [Core Business Workflow](#core-business-workflow)
* [Partner Management](#partner-management)
* [Approval System](#approval-system)
* [Investment & Withdrawal](#investment--withdrawal)
* [Inventory Management](#inventory-management)
* [Sales Management](#sales-management)
* [Promotion Management](#promotion-management)
* [Expense Management](#expense-management)
* [Accounting](#accounting)
* [Reports](#reports)
* [User Roles](#user-roles)
* [Technology Stack](#technology-stack)
* [System Architecture](#system-architecture)
* [Database Modules](#database-modules)
* [Installation](#installation)
* [Docker Setup](#docker-setup)
* [Environment Configuration](#environment-configuration)
* [Database Setup](#database-setup)
* [Development Workflow](#development-workflow)
* [Testing](#testing)
* [API](#api)
* [Security](#security)
* [Accounting Principles](#accounting-principles)
* [Inventory Principles](#inventory-principles)
* [Future Improvements](#future-improvements)
* [License](#license)

---

# Overview

This application manages the complete financial and operational activities of a multi-partner store.

The system supports:

* Multiple business partners
* Individual partner investments
* Partner withdrawals
* Partner expenses
* Partner promotion contributions
* Partner profit allocation
* Partner-to-partner transfers
* Multi-level transaction approval
* Product management
* Supplier management
* Purchase management
* Inventory management
* Sales management
* Customer management
* Promotion management
* Business expenses
* Cash and bank accounts
* Double-entry accounting
* General ledger
* Trial balance
* Profit & Loss
* Balance Sheet
* Monthly business reports
* Partner statements
* Audit logs
* Notifications
* Role-based permissions

The primary goal is to maintain a **single source of truth for financial and inventory data**.

---

# Key Features

## Partner Management

Manage multiple business partners.

Each partner can have:

* Name
* Partner code
* Phone
* Email
* Address
* Joining date
* Ownership percentage
* Investment percentage
* User account
* Status

Partner statuses:

* Active
* Inactive
* Suspended

---

## Partner Financial Management

Track each partner independently.

The system records:

* Investments
* Withdrawals
* Promotion contributions
* Business expenses
* Profit shares
* Capital transfers
* Adjustments

Each partner has a complete statement.

Example:

| Date   | Description  |   Debit |   Credit |  Balance |
| ------ | ------------ | ------: | -------: | -------: |
| 01-Jan | Investment   |         | ৳500,000 | ৳500,000 |
| 10-Feb | Promotion    | ৳20,000 |          | ৳480,000 |
| 15-Mar | Withdrawal   | ৳50,000 |          | ৳430,000 |
| 31-Mar | Profit Share |         |  ৳25,000 | ৳455,000 |

---

# Approval System

The system uses a **Partner Approval / Dual-Control workflow** for important transactions.

A user who creates a transaction cannot approve their own transaction.

### Example

```text
Partner A
   |
   | Creates withdrawal
   ↓
Pending Approval
   |
   ↓
Partner B
   |
   | Approves
   ↓
Approved
   |
   ↓
Accounting Entry
```

If rejected:

```text
Partner A
   |
   ↓
Withdrawal Request
   |
   ↓
Partner B
   |
   ↓
Rejected
```

The rejected transaction does not become a finalized accounting transaction.

---

# Approval Types

The approval system can be used for:

* Partner investments
* Partner withdrawals
* Partner expenses
* Promotion expenses
* Purchases
* Stock adjustments
* Refunds
* Partner transfers
* Large discounts
* Sales cancellations
* Other configurable transactions

---

# Multiple Approval Levels

The system supports configurable approval requirements.

Example:

```text
৳0 - ৳10,000
1 approval

৳10,001 - ৳100,000
2 approvals

৳100,000+
3 approvals
```

Approval thresholds should be configurable rather than hard-coded.

---

# Approval Rules

The following rules are mandatory:

1. A user cannot approve their own tr
