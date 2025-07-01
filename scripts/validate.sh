#!/bin/bash

# Simple WordPress Architecture Validation
# Usage: ./scripts/validate.sh [check_type]

set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC_DIR="$PROJECT_ROOT/src/App"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

check_field_service_compliance() {
    echo "🔍 Checking Field Service Compliance..."
    
    # Find direct ACF field access outside of FieldService classes
    violations=$(find "$SRC_DIR" -name "*.php" -not -path "*/Base/*" -not -name "*FieldService.php" -exec grep -l "get_field\|update_field\|the_field" {} \; 2>/dev/null || true)
    
    if [ -n "$violations" ]; then
        echo -e "${RED}❌ Field Service violations found:${NC}"
        echo "$violations" | while read -r file; do
            echo "  • $(basename "$file")"
            grep -n "get_field\|update_field\|the_field" "$file" | head -2 | sed 's/^/    /'
        done
        return 1
    else
        echo -e "${GREEN}✅ All field access uses centralized FieldService${NC}"
        return 0
    fi
}

check_file_sizes() {
    echo "🔍 Checking File Sizes (>350 lines)..."
    
    large_files=$(find "$SRC_DIR" -name "*.php" -exec wc -l {} + | awk '$1 > 350 {print $2 " (" $1 " lines)"}' | grep -v total || true)
    
    if [ -n "$large_files" ]; then
        echo -e "${YELLOW}⚠️  Large files found:${NC}"
        echo "$large_files" | while read -r line; do
            echo "  • $line"
        done
        echo -e "${YELLOW}Consider splitting these files for better maintainability${NC}"
        return 1
    else
        echo -e "${GREEN}✅ All files under 350 lines${NC}"
        return 0
    fi
}

check_domain_boundaries() {
    echo "🔍 Checking Domain Boundaries..."
    
    # Look for problematic cross-domain imports (Realizace <-> Faktury direct coupling)
    # Exclude Base/, Services/, and Shortcodes/ which are integration points
    violations=$(find "$SRC_DIR" -path "*/Realizace/*" -name "*.php" -exec grep -l "use.*MistrFachman\\\\Faktury\\\\" {} \; 2>/dev/null || true)
    violations+=" "$(find "$SRC_DIR" -path "*/Faktury/*" -name "*.php" -exec grep -l "use.*MistrFachman\\\\Realizace\\\\" {} \; 2>/dev/null || true)
    violations+=" "$(find "$SRC_DIR" -path "*/Users/*" -name "*.php" -exec grep -l "use.*MistrFachman\\\\\\(Realizace\\|Faktury\\)" {} \; 2>/dev/null || true)
    
    # Remove extra spaces and empty entries
    violations=$(echo "$violations" | tr ' ' '\n' | grep -v '^$' | head -5 || true)
    
    if [ -n "$violations" ]; then
        echo -e "${RED}❌ Problematic cross-domain coupling found:${NC}"
        echo "$violations" | while read -r file; do
            if [ -n "$file" ]; then
                echo "  • $(basename "$file")"
            fi
        done
        return 1
    else
        echo -e "${GREEN}✅ Clean domain boundaries maintained${NC}"
        echo -e "${GREEN}    (Shortcodes and MyCred integration points are allowed)${NC}"
        return 0
    fi
}

# Main execution
case "${1:-all}" in
    "field-service"|"fields")
        check_field_service_compliance
        ;;
    "file-size"|"size")
        check_file_sizes
        ;;
    "domain-boundaries"|"domains")
        check_domain_boundaries
        ;;
    "all")
        echo "🏗️  WordPress Architecture Validation"
        echo "=================================="
        echo
        
        field_ok=0
        size_ok=0
        domain_ok=0
        
        check_field_service_compliance || field_ok=1
        echo
        check_file_sizes || size_ok=1
        echo
        check_domain_boundaries || domain_ok=1
        echo
        
        total_issues=$((field_ok + size_ok + domain_ok))
        
        if [ $total_issues -eq 0 ]; then
            echo -e "${GREEN}🎉 All checks passed!${NC}"
            exit 0
        else
            echo -e "${RED}❌ $total_issues check(s) failed${NC}"
            exit 1
        fi
        ;;
    *)
        echo "Usage: $0 [field-service|file-size|domain-boundaries|all]"
        exit 1
        ;;
esac