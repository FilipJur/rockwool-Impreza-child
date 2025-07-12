# Architecture Consistency Refactor Plan: Template Method Pattern for Points System

## Executive Summary

This plan addresses critical architectural inconsistencies discovered in the WordPress points system implementation. Through comprehensive audit, we identified significant DRY violations and inconsistent patterns between the Faktury and Realizace domains that create maintenance risks and technical debt.

**Key Issue**: Two domains implementing points awarding using completely different architectural approaches, leading to code duplication and maintenance overhead.

**Solution**: Implement Template Method Pattern to unify architecture while preserving domain-specific requirements.

---

## Background Context

### Current Implementation Analysis

The points system underwent a complex refactoring journey to eliminate race conditions between ACF field calculations and points awarding. This led to two different solutions:

**Faktury Domain (Custom Hybrid Approach):**
- Uses `acf/save_post` priority 20 for calculation
- Uses `acf/save_post` priority 25 for awarding 
- Uses `transition_post_status` for revocations
- Completely overrides `init_hooks()` and bypasses base class
- Reimplements awarding logic in custom methods

**Realizace Domain (Base Class Approach):**
- Uses `PointsHandlerBase::init_hooks()` with `wp_after_insert_post`
- Inherits all awarding/revocation logic from base class
- Simple extension with only abstract method implementations

### Why Different Approaches Emerged

1. **Faktury's Specific Need**: Dynamic points calculation based on invoice values required running after ACF field saves to avoid race conditions
2. **Realizace's Simplicity**: Fixed 2500 points calculation worked fine with base class `wp_after_insert_post` approach
3. **Time Pressure**: Faktury needed immediate fix, leading to bypass of abstractions

---

## Critical Architecture Problems Identified

### 1. Significant Code Duplication

**Awarding Logic Duplication:**
```php
// Faktury/PointsHandler.php
public function handle_awarding_after_calculation($post_id): void {
    // 50+ lines of awarding logic
}

// Base/PointsHandlerBase.php  
public function handle_post_after_insert(int $post_id, \WP_Post $post, bool $update, ?\WP_Post $post_before): void {
    // Nearly identical 50+ lines of awarding logic
}
```

**Revocation Logic Duplication:**
```php
// Faktury/PointsHandler.php
public function handle_status_transition(string $new_status, string $old_status, \WP_Post $post): void {
    // 30+ lines of revocation logic
}

// Base/PointsHandlerBase.php
protected function revoke_points(int $post_id, int $user_id, string $new_status): void {
    // Similar revocation logic
}
```

### 2. Inconsistent Architecture Patterns

| Aspect | Faktury Domain | Realizace Domain |
|--------|---------------|------------------|
| Hook Strategy | Custom ACF + Transition | Base Class wp_after_insert_post |
| Code Reuse | Bypasses abstractions | Uses abstractions |
| Maintenance | Domain-specific fixes | Inherits base fixes |
| Complexity | High (custom implementations) | Low (extends base) |

### 3. Maintenance and Development Risks

**Current Issues:**
- ❌ Bug fixes must be applied in multiple places
- ❌ New developers must learn two different patterns  
- ❌ Future domains have no clear pattern to follow
- ❌ High cognitive load for maintenance
- ❌ Risk of behavioral divergence over time

---

## Gemini AI Expert Analysis

### Key Findings from AI Audit

**Gemini's Assessment:**
> "This is a high-risk architecture... From a software engineering perspective, this level of duplication and inconsistency is absolutely not acceptable."

**Specific Concerns Identified:**
1. **Bug Proneness**: "A bug fixed in the base class's `award_points()` method will likely need to be fixed separately in `Faktury\PointsHandler`"
2. **Cognitive Load**: "A new developer has to understand two completely different implementation patterns"
3. **Maintenance Overhead**: "Every change requires analyzing and potentially modifying two separate, divergent codebases"

**Recommended Solution:**
> "The goal is to unify the architecture by moving the responsibility for *registering* hooks into the base class, while allowing subclasses to *define* what those hooks are. This is a classic application of the **Template Method Pattern**."

---

## Proposed Solution: Template Method Pattern

### Core Concept

Create a flexible base class that:
- **Provides**: Core awarding/revocation logic (the "what")
- **Allows**: Subclasses to configure hooks and timing (the "when")
- **Prevents**: Code duplication while maintaining domain flexibility

### Architecture Design

```php
abstract class PointsHandlerBase {
    // Subclasses declare their preferred awarding hook
    abstract protected function get_awarding_hook(): array;
    
    // Subclasses can override revocation hook (default: transition_post_status)
    protected function get_revocation_hook(): array;
    
    // UNIFIED - no longer overrideable
    final public function init_hooks(): void;
    
    // UNIFIED - single entry point for all awarding
    public function handle_awarding_trigger(...$args): void;
    
    // UNIFIED - single entry point for all revocation  
    public function handle_revocation_trigger(...$args): void;
    
    // EXISTING - core business logic (unchanged)
    public function award_points(int $post_id, int $user_id, int $points_to_award): bool;
    protected function revoke_points(int $post_id, int $user_id, string $new_status): void;
}
```

---

## Implementation Plan

### Phase 1: Refactor Base Class Architecture

**File**: `src/App/Base/PointsHandlerBase.php`

**Changes:**
1. **Add Abstract Hook Definition Methods:**
   ```php
   abstract protected function get_awarding_hook(): array;
   protected function get_revocation_hook(): array; // with default
   ```

2. **Create Unified Trigger Handlers:**
   ```php
   public function handle_awarding_trigger(...$args): void {
       $post_id = $this->extract_post_id_from_hook_args($args);
       // Use existing award_points() logic
   }
   ```

3. **Make init_hooks() Final and Flexible:**
   ```php
   final public function init_hooks(): void {
       $awarding_hook = $this->get_awarding_hook();
       add_action($awarding_hook['name'], [$this, 'handle_awarding_trigger'], 
                 $awarding_hook['priority'], $awarding_hook['accepted_args']);
   }
   ```

**Benefits:**
- Preserves all existing core logic
- Makes hook configuration declarative
- Prevents future bypass attempts

### Phase 2: Simplify Faktury Domain

**File**: `src/App/Faktury/PointsHandler.php`

**Remove Entirely:**
- `init_hooks()` method override
- `handle_awarding_after_calculation()` method  
- `handle_status_transition()` method
- Any other custom hook implementations

**Add Only:**
```php
protected function get_awarding_hook(): array {
    return [
        'name' => 'acf/save_post',
        'priority' => 25,
        'accepted_args' => 1
    ];
}
```

**Result:**
- ~200 lines of code removed
- Maintains exact same functionality
- Uses base class logic for all operations

### Phase 3: Verify Realizace Domain

**File**: `src/App/Realizace/PointsHandler.php`

**Add Only:**
```php
protected function get_awarding_hook(): array {
    return [
        'name' => 'wp_after_insert_post',
        'priority' => 10,
        'accepted_args' => 4
    ];
}
```

**Result:**
- Maintains existing behavior
- Explicit hook declaration
- Simplified inheritance

### Phase 4: Integration Testing

**Test Scenarios:**
1. **Faktury Invoice Value Changes**: Verify ACF priority sequence still works
2. **Faktury Status Changes**: Verify revocation on unpublish
3. **Realizace Status Changes**: Verify existing behavior unchanged
4. **Cross-Domain**: Verify no interference between domains

---

## Expected Benefits

### Immediate Improvements

✅ **DRY Compliance**: Eliminate ~300 lines of duplicated code  
✅ **Single Source of Truth**: Core logic only in base class  
✅ **Consistent Architecture**: All domains follow same pattern  
✅ **Reduced Complexity**: Simpler mental model for developers  

### Long-Term Benefits

✅ **Easier Maintenance**: Bug fixes and improvements in one place  
✅ **Predictable Extension**: New domains just declare hooks  
✅ **Better Testing**: Centralized logic easier to test comprehensively  
✅ **Documentation Clarity**: Single pattern to document and explain  

### Performance Benefits

✅ **No Performance Impact**: Same hooks, same timing, unified logic  
✅ **Reduced Memory**: Less code duplication  
✅ **Faster Development**: Clear patterns reduce implementation time  

---

## Risk Mitigation Strategy

### Implementation Safety

1. **Phased Approach**: Implement changes incrementally with testing
2. **Behavior Preservation**: Ensure exact same functionality after refactor
3. **Comprehensive Logging**: Verify operations work identically  
4. **Rollback Plan**: Each phase can be reverted independently

### Testing Strategy

1. **Unit Tests**: Test base class hook configuration  
2. **Integration Tests**: Verify domain-specific requirements
3. **Regression Tests**: Ensure no behavioral changes
4. **Performance Tests**: Verify no performance degradation

### Validation Criteria

- [ ] Faktury ACF priority sequence works identically
- [ ] Faktury revocation on status change works identically  
- [ ] Realizace existing behavior unchanged
- [ ] No duplicate awarding in any scenario
- [ ] Debug logs show clean execution paths
- [ ] Code review confirms DRY compliance

---

## File Structure After Refactor

```
src/App/Base/
└── PointsHandlerBase.php        # Unified template with hook configuration

src/App/Faktury/
├── PointsHandler.php            # Declares ACF hooks only
├── AdminController.php          # Unchanged
├── ValidationService.php        # Unchanged  
└── FakturaFieldService.php      # Unchanged

src/App/Realizace/
├── PointsHandler.php            # Declares wp_after_insert_post only
├── AdminController.php          # Unchanged
└── RealizaceFieldService.php    # Unchanged
```

**Code Reduction:**
- **Faktury**: ~200 lines removed (custom implementations)
- **Base**: ~50 lines added (flexibility methods)
- **Net**: ~150 lines removed while improving architecture

---

## Success Metrics

### Quantitative Measures

- **Code Duplication**: Reduce from ~300 duplicated lines to 0
- **Cyclomatic Complexity**: Reduce average complexity per domain
- **Maintenance Burden**: Single point of change for core logic
- **Development Time**: Faster implementation of new domains

### Qualitative Measures

- **Architecture Clarity**: Clear, consistent patterns across domains
- **Developer Experience**: Easier onboarding and maintenance
- **System Reliability**: Reduced bug surface area
- **Code Quality**: Better adherence to SOLID principles

---

## Implementation Notes for AI Agent

### Context for New Agent

1. **Current State**: Two domains with completely different hook strategies
2. **Working Functionality**: Both domains work correctly, just architecturally inconsistent
3. **Performance**: Current hybrid approach is fast and reliable
4. **Goal**: Unify architecture without changing behavior

### Key Constraints

- **Preserve Functionality**: Exact same behavior required
- **Maintain Performance**: No performance degradation acceptable  
- **Honor Domain Needs**: Faktury needs ACF timing, Realizace needs simplicity
- **Future Extensibility**: Enable easy addition of new domains

### Implementation Priority

1. **Base Class Flexibility** (highest impact)
2. **Faktury Simplification** (removes most duplication)  
3. **Realizace Verification** (ensures no regression)
4. **Testing and Validation** (confirms success)

This refactor represents a critical architectural improvement that will significantly reduce technical debt while maintaining all existing functionality and performance characteristics.