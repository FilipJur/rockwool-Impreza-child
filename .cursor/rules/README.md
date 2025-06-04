# Cursor Rules Documentation

This directory contains comprehensive development guidelines for the myCred WooCommerce integration in the Impreza child theme.

## Rule Files

### 📋 [mycred-php.mdc](./mycred-php.mdc)
**PHP Development Standards**
- Class naming and structure guidelines
- Singleton pattern implementation
- Dependency injection patterns
- Documentation requirements
- Security and performance guidelines
- Common patterns and anti-patterns

### 🏗️ [architecture.mdc](./architecture.mdc)
**System Architecture Overview**
- Component relationships and data flow
- Design patterns used (Singleton, Dependency Injection)
- Integration points with WordPress/WooCommerce/myCred
- State management and error handling
- Extensibility points for future development

### 🧪 [testing.mdc](./testing.mdc)
**Comprehensive Testing Guidelines**
- Unit testing scenarios for all components
- Integration testing with WooCommerce and myCred
- Manual testing procedures and edge cases
- Performance and load testing strategies
- Test data setup and debugging tools

## File Naming Convention

- **`.mdc`** extension for Markdown-based Cursor rules
- **kebab-case** naming for consistency
- **Descriptive names** indicating content scope

## Usage

These rules are designed for:
- **Cursor AI Editor** - Primary IDE integration
- **Development Teams** - Consistent coding standards
- **Code Reviews** - Reference documentation
- **New Developers** - Onboarding guidelines

## Integration with Project

### Related Documentation
- **`/CLAUDE.md`** - Project-wide development guidelines
- **`/src/includes/mycred/`** - Implementation files
- **`/docs/design/`** - Design system documentation

### Development Workflow
1. Review relevant `.mdc` files before coding
2. Follow patterns and standards outlined
3. Reference architecture for component relationships
4. Use testing guidelines for quality assurance

## Maintenance

These rules should be updated when:
- New components are added to the myCred system
- Development patterns change or evolve
- WordPress, WooCommerce, or myCred APIs change
- Team coding standards are modified

## Quick Reference

| Need | File | Section |
|------|------|---------|
| Class structure | `mycred-php.mdc` | Coding Standards |
| Component relationships | `architecture.mdc` | Component Overview |
| Testing scenarios | `testing.mdc` | Testing Categories |
| Security patterns | `mycred-php.mdc` | Security Guidelines |
| Performance tips | `architecture.mdc` | Performance Considerations |
| Debugging help | `testing.mdc` | Debugging Testing Issues |

---

*These rules ensure consistent, maintainable, and high-quality code for the myCred WooCommerce integration.*