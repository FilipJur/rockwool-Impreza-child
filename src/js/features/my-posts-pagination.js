/**
 * Unified pagination handler for my-posts shortcodes
 * Handles both my-realizace and my-faktury shortcodes with numbered pagination
 */

export function setupMyPostsPagination() {
    // Handle both realizace and faktury shortcodes
    const shortcodeTypes = ['my-realizace', 'my-faktury'];

    shortcodeTypes.forEach(shortcodeType => {
        const containers = document.querySelectorAll(`.${shortcodeType}-shortcode`);

        containers.forEach(container => {	
            const paginationEnabled = container.dataset.enablePagination === 'true';

            if (!paginationEnabled) {
                return;
            }

            setupPaginationForContainer(container, shortcodeType);
        });
    });
}

function setupPaginationForContainer(container, shortcodeType) {
    const loadingDiv = container.querySelector(`.${shortcodeType}-loading`);
    const postsContainer = container.querySelector(`.${shortcodeType}-posts`);
    const paginationContainer = container.querySelector(`.${shortcodeType}-pagination`);

    if (!loadingDiv || !postsContainer || !paginationContainer) {
        return;
    }

    let currentPage = 1;
    let totalPages = parseInt(container.dataset.totalPages) || 1;
    let isLoading = false;

    // Initialize pagination
    renderPagination();

    function renderPagination() {
        paginationContainer.innerHTML = '';

        if (totalPages <= 1) {
            return;
        }

        const nav = document.createElement('nav');
        nav.className = 'flex justify-center space-x-1 mt-6';

        // Previous button
        if (currentPage > 1) {
            const prevBtn = createPaginationButton('‹', currentPage - 1);
            prevBtn.className = 'px-3 py-2 border';
            nav.appendChild(prevBtn);
        }

        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);

        if (startPage > 1) {
            nav.appendChild(createPaginationButton('1', 1));
            if (startPage > 2) {
                const ellipsis = document.createElement('span');
                ellipsis.className = 'px-3 py-2';
                ellipsis.textContent = '...';
                nav.appendChild(ellipsis);
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = createPaginationButton(i.toString(), i);
            if (i === currentPage) {
                pageBtn.className = 'px-3 py-2 border font-bold';
            } else {
                pageBtn.className = 'px-3 py-2 border';
            }
            nav.appendChild(pageBtn);
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                const ellipsis = document.createElement('span');
                ellipsis.className = 'px-3 py-2';
                ellipsis.textContent = '...';
                nav.appendChild(ellipsis);
            }
            nav.appendChild(createPaginationButton(totalPages.toString(), totalPages));
        }

        // Next button
        if (currentPage < totalPages) {
            const nextBtn = createPaginationButton('›', currentPage + 1);
            nextBtn.className = 'px-3 py-2 border';
            nav.appendChild(nextBtn);
        }

        paginationContainer.appendChild(nav);
    }

    function createPaginationButton(text, page) {
        const button = document.createElement('button');
        button.textContent = text;
        button.addEventListener('click', (e) => {
            e.preventDefault();
            loadPage(page);
        });
        return button;
    }

    async function loadPage(page) {
        if (isLoading || page === currentPage) return;

        isLoading = true;
        loadingDiv.classList.remove('hidden');

        try {
            const response = await fetch(window.mistrFachman.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: `${shortcodeType.replace('-', '_')}_load_page`,
                    nonce: container.dataset.nonce,
                    page: page,
                    posts_per_page: container.dataset.postsPerPage,
                    show_content: container.dataset.showContent,
                }),
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                // Replace posts content
                postsContainer.innerHTML = data.data.html;
                
                // Update pagination info
                currentPage = page;
                totalPages = data.data.total_pages || totalPages;
                
                // Re-render pagination
                renderPagination();
                
                // Scroll to top of posts container
                postsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });

            } else {
                throw new Error(data.data?.message || 'Unknown error');
            }

        } catch (error) {
            console.error('Error loading page:', error);

            // Show error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-sm mt-2';
            errorDiv.textContent = 'Chyba při načítání příspěvků. Zkuste to prosím znovu.';
            postsContainer.appendChild(errorDiv);

        } finally {
            isLoading = false;
            loadingDiv.classList.add('hidden');
        }
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupMyPostsPagination);
} else {
    setupMyPostsPagination();
}
