# Google-Quality Coding Standards

**Philosophy**: Write code as if the person who ends up maintaining your code is a violent psychopath who knows where you live. (And also a Principal Engineer at Google).

## 1. Architectural Patterns

### Service Provider Architecture
- **No God Objects**: Break monolithic classes into focused **Services**.
- **Dependency Injection**: Never use `new Class()` inside another class. Inejct dependencies via the constructor.
- **Micro-Kernel**: Use `OMS\Core\Kernel` to boot the application.
- **Providers**: Use `ServiceProvider` classes to wire services into the `Container`.

### Strict Dependency Injection
- **Forbidden**: `global $wpdb;` inside methods.
- **Required**: Inject `wpdb` (or a `DatabaseService` wrapper) into the constructor.
- **Forbidden**: `OMS_Plugin::get_instance()` (Singleton Anti-pattern access).
- **Required**: Pass instances via the DI Container.

## 2. Modern PHP (8.4+)

- **Strict Types**: Always `declare(strict_types=1);` at the top of every file.
- **Constructor Promotion**: Use `public function __construct(private readonly Service $service) {}`.
- **Return Types**: Every method must have a return type. `function foo(): void`.
- **Typed Properties**: `private string $name;`.
- **Null Safety**: Use `?string` or `string|null` explicitly.

## 3. WordPress Security

- **Nonces**: Verify nonces for ALL admin actions.
- **Capabilities**: Check `current_user_can()` for ALL privileged actions.
- **Escaping**: Escape everything on output (`esc_html`, `esc_attr`). Late escaping is preferred.
- **Filesystem**: Use wrapper services (e.g., `FilesystemService`) instead of raw PHP functions where possible, or document deviations with `// phpcs:ignore`.

## 4. Testing (TDD)

- **Unit Tests**: Every Service must have a corresponding Unit Test in `tests/Unit`.
- **Mocking**: Mock external dependencies (filesystem, database, other services).
- **Coverage**: Aim for 100% path coverage on business logic.

## 5. Legacy Code Strategy

- **Do Not Delete**: Move deprecated/legacy code to the `legacy/` directory.
- **Do Not Load**: Ensure legacy files are **not** loaded by the Kernel.
- **Refactor**: Rewrite logic in new Services, then verify, then retire the old code.
