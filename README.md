# Laravel ModelSchema

<img src="new_logo.png" alt="Laravel ModelSchema" width="200">

Define and manage Laravel model schemas in clean YAML files — and export them to JSON, PHP arrays, or other formats. This package serves as a unified schema core that powers tools like Laravel Arc and Laravel TurboMaker.

[![Latest Version](https://img.shields.io/packagist/v/grazulex/laravel-modelschema.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-modelschema)
[![Total Downloads](https://img.shields.io/packagist/dt/grazulex/laravel-modelschema.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-modelschema)
[![License](https://img.shields.io/github/license/grazulex/laravel-modelschema.svg?style=flat-square)](https://github.com/Grazulex/laravel-modelschema/blob/main/LICENSE.md)
[![PHP Version](https://img.shields.io/packagist/php-v/grazulex/laravel-modelschema.svg?style=flat-square)](https://php.net/)
[![Laravel Version](https://img.shields.io/badge/laravel-12.x-ff2d20?style=flat-square&logo=laravel)](https://laravel.com/)
[![Tests](https://img.shields.io/github/actions/workflow/status/grazulex/laravel-modelschema/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Grazulex/laravel-modelschema/actions)
[![Code Style](https://img.shields.io/badge/code%20style-pint-000000?style=flat-square&logo=laravel)](https://github.com/laravel/pint)

## Overview

Laravel ModelSchema is a foundational package for defining and parsing model schemas using YAML. It provides a unified, extensible structure to describe models, fields, relations, and options — and serves as the YAML engine behind Laravel Arc, Laravel TurboMaker, and future Grazulex packages.

**Think of it as the single source of truth for describing your models and DTOs across multiple tools.**

### 🎯 Key Features

- 🧾 YAML Schema Parsing - Consistent parsing and validation of model YAML files
- 🧱 Extensible Structure - Supports fields, relations, metadata, options, and more
- 🔄 Multi-format Output - Convert schemas to JSON, PHP arrays, or plain objects
- 🔍 Validation API - Strict schema validation with helpful error feedback
- 🛠️ Reusable Core - Integrates into Laravel Arc, TurboMaker, and other tools
- 📦 Standalone or Embedded - Use it directly or from other packages

### 📚 Complete Documentation

➡️ Visit the Wiki for complete documentation, schema examples, and integration guides:
https://github.com/Grazulex/laravel-modelschema/wiki

The wiki contains:
- Getting Started Guide
- Schema Structure
- Output Formats
- Integration Tips
- Examples

## 📦 Quick Installation

```bash
composer require grazulex/laravel-modelschema
```

## 🚀 Quick Start

1. Create a schema definition:

```yaml
model: Product
table: products
fields:
  name:
    type: string
    nullable: false
  price:
    type: decimal:8,2
    rules: ['required', 'min:0']
relations:
  category:
    type: belongsTo
    model: App\Models\Category
options:
  timestamps: true
  soft_deletes: false
```

2. Parse and use your schema:

```php
use Grazulex\ModelSchema\ModelSchema;

$schema = ModelSchema::fromYamlFile('resources/schemas/product.yaml');

$fields = $schema->fields();
$asArray = $schema->toArray();
```

## 📖 Learn More

- 📚 Complete Documentation – Full guides and API reference
- 🚀 Getting Started – Installation and usage
- 🧾 Schema Reference – Schema field details
- 🔧 Advanced Integration – Embed into other tools

## 🔧 Requirements

- PHP: ^8.3
- Laravel: ^12.19 (optional, but used in integration)

## 🧪 Testing

```bash
composer test
```

## 🤝 Contributing

We welcome contributions! Please see our Contributing Guide for details.

## 🔒 Security

Please review our Security Policy for reporting vulnerabilities.

## 📄 License

Laravel ModelSchema is open-sourced software licensed under the MIT license.

---

Made with ❤️ by Jean-Marc Strauven (https://github.com/Grazulex)