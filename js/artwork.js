/**
 * ArtLoop - Artwork Page JavaScript
 * This file contains client-side functionality for the artwork details page
 */

document.addEventListener('DOMContentLoaded', function() {
    // Like artwork functionality
    const likeButton = document.querySelector('.like-artwork');
    if (likeButton) {
        likeButton.addEventListener('click', function() {
            const artworkId = this.getAttribute('data-artwork-id');
            likeArtwork(artworkId, this);
        });
    }
    
    // Add to cart functionality
    const addToCartButton = document.querySelector('.add-to-cart');
    if (addToCartButton) {
        addToCartButton.addEventListener('click', function() {
            const artworkId = this.getAttribute('data-artwork-id');
            addToCart(artworkId);
        });
    }
});

/**
 * Like or unlike an artwork
 * @param {number} artworkId - The ID of the artwork to like/unlike
 * @param {HTMLElement} button - The like button element
 */
function likeArtwork(artworkId, button) {
    fetch('/php/like_artwork.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'artwork_id=' + artworkId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update like count
            const likeCount = button.querySelector('.like-count');
            if (likeCount) {
                likeCount.innerText = data.likeCount;
            }
            
            // Update like text
            const likeText = button.querySelector('.like-text');
            if (likeText) {
                likeText.innerText = data.liked ? 'Liked' : 'Like';
            }
            
            // Toggle like button state
            if (data.liked) {
                button.classList.add('liked');
                button.querySelector('i').classList.remove('far');
                button.querySelector('i').classList.add('fas');
            } else {
                button.classList.remove('liked');
                button.querySelector('i').classList.remove('fas');
                button.querySelector('i').classList.add('far');
            }
            
            // Show success message
            showToast('Success', data.liked ? 'Artwork liked!' : 'Artwork unliked!', 'success');
        } else {
            showToast('Error', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'An error occurred. Please try again.', 'error');
    });
}

/**
 * Add artwork to cart
 * @param {number} artworkId - The ID of the artwork to add to cart
 */
function addToCart(artworkId) {
    fetch('/php/add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'artwork_id=' + artworkId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart count in header
            const cartCount = document.getElementById('cart-count');
            if (cartCount) {
                cartCount.innerText = data.cartCount;
            }
            
            // Show success message
            showToast('Success', 'Artwork added to cart!', 'success');
            
            // Disable add to cart button if needed
            if (data.inCart) {
                const addToCartButton = document.querySelector('.add-to-cart');
                if (addToCartButton) {
                    addToCartButton.disabled = true;
                    addToCartButton.innerHTML = '<i class="fas fa-check me-2"></i>Added to Cart';
                }
            }
        } else {
            showToast('Error', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'An error occurred. Please try again.', 'error');
    });
}

/**
 * Show toast notification
 * @param {string} title - The toast title
 * @param {string} message - The toast message
 * @param {string} type - The toast type (success, error, warning, info)
 */
function showToast(title, message, type) {
    // Check if toast container exists, if not create it
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast element
    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'info'}`;
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    
    // Create toast content
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <strong>${title}</strong>: ${message}
            </div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    // Add toast to container
    toastContainer.appendChild(toastEl);
    
    // Initialize and show toast
    const toast = new bootstrap.Toast(toastEl, {
        autohide: true,
        delay: 5000
    });
    toast.show();
}