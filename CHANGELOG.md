# Changelog

## 2.0.0 (2025-11-05)

This is a major release that introduces several new features, improvements, and bug fixes. Please refer to the [migration guide](MIGRATION.md) for details on how to upgrade from v1 to v2.

### Features

- **api:** add `/v2/payment-request/invoices`
- **api:** add `/v1/payouts`
- **api:** add `/v1/payouts-account`
- **client:** add default value for credential read from environment variable
- **client:** add `PayOS\Crypto\CryptoProvider` to calculate signature for payment-requests and payouts signature
- **client:** add additional options to all method
- **client:** add logging with custom logger
- **client:** add pagination support for get list request
- **client:** add retry for rate limit request
- **client:** add Error subclass to handle api error, webhook error and signature error for better error handling
- **client:** add support for request download file

### Documentation

- **readme:** update readme

## 1.0.5 (2024-06-27)

### Features

- **client:** Add partner code in payOS configuration

## 1.0.4 (2024-03-22)

### Bug fixes

- **client:** Modify method name

## 1.0.3 (2024-01-31)

### Bug fixes

- **client:** Response code of confirm webhook

## 1.0.1 (2024-01-17)

### Bug fixes

- **client:** Signature from json array

## 1.0.0 (2024-01-12)

## 0.0.1 (2023-12-19)

### Features

- **client:** Create payment link
- **client:** Get payment link information
- **client:** Cancel payment link
- **client:** Confirm webhook
- **client:** Verify payment webhook data
