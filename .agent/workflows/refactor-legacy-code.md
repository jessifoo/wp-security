---
description: Refactor a legacy PHP Class/God Object into modern Architecture
---

# Refactor Legacy Code to Service Architecture

This workflow guides you through dismantling a "God Object" or legacy class into a modern, testable Service Provider architecture.

## 1. Analysis & Interface Definition
- [ ] Identify the specific responsibility to extract (e.g., "File Scanning" from "MalwareScanner").
- [ ] Create an **Interface** in `includes/Interfaces/`.
- [ ] **Rule**: The Interface should expose only public business logic methods.

## 2. Service Implementation (TDD)
- [ ] Create a **Unit Test** in `tests/Unit/Services/` *before* the implementation.
    - [ ] Mock all dependencies (Logger, Database, etc.).
- [ ] Create the **Service Class** in `includes/Services/`.
    - [ ] `declare(strict_types=1);`
    - [ ] Implement the Interface.
    - [ ] Inject dependencies via `__construct`.
- [ ] Run tests: `vendor/bin/phpunit tests/Unit/...`

## 3. Provider Registration
- [ ] Create (or update) a **ServiceProvider** in `includes/Providers/`.
- [ ] Register the Service in the `register()` method:
    ```php
    $container->singleton(MyServiceInterface::class, function($c) {
        return new MyService($c->get(Dependency::class));
    });
    ```
- [ ] Register Hooks in `boot()` if necessary (for Actions/Filters).

## 4. Kernel Integration
- [ ] Ensure the Provider is listed in `includes/Core/Kernel.php` (or your bootstrapping logic).
- [ ] Update `check_architecture.php` to verify the new Service resolves correctly.

## 5. Legacy Retirement
- [ ] Move the old file to `legacy/`.
- [ ] Update the main plugin file to stop loading the old class.
- [ ] Verify the system still boots.

## 6. Compliance Check
// turbo
- [ ] Run PHPCS check
    ```bash
    composer run phpcs -- --standard=WordPress includes/Services/YourNewService.php
    ```
