/**
 * ArtLoop - Main JavaScript File
 * This file contains all the client-side functionality for the ArtLoop application
 */

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Form validation for registration form
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(event) {
            if (!validateRegisterForm()) {
                event.preventDefault();
            }
        });
    }

    // Form validation for login form
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(event) {
            if (!validateLoginForm()) {
                event.preventDefault();
            }
        });
    }

    // Form validation for artwork upload form
    const uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(event) {
            if (!validateUploadForm()) {
                event.preventDefault();
            }
        });
    }

    // Add to cart functionality
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault();
            const artworkId = this.getAttribute('data-artwork-id');
            addToCart(artworkId);
        });
    });

    // Like artwork functionality is handled in artwork.js

    // Search form auto-submit on category change
    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function() {
            this.closest('form').submit();
        });
    }

    // Image preview for artwork upload
    const artworkImage = document.getElementById('artworkImage');
    const imagePreview = document.getElementById('imagePreview');
    if (artworkImage && imagePreview) {
        artworkImage.addEventListener('change', function() {
            previewImage(this, imagePreview);
        });
    }

    // Quantity update in cart
    const quantityInputs = document.querySelectorAll('.cart-quantity');
    quantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            const cartItemId = this.getAttribute('data-cart-item-id');
            const quantity = this.value;
            updateCartQuantity(cartItemId, quantity);
        });
    });
});

/**
 * Validate registration form
 * @returns {boolean} True if form is valid, false otherwise
 */
function validateRegisterForm() {
    let isValid = true;
    const username = document.getElementById('username');
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirmPassword');

    // Reset error messages
    resetErrors();

    // Validate username
    if (username.value.trim() === '') {
        showError(username, 'Username is required');
        isValid = false;
    } else if (username.value.length < 3) {
        showError(username, 'Username must be at least 3 characters');
        isValid = false;
    }

    // Validate email
    if (email.value.trim() === '') {
        showError(email, 'Email is required');
        isValid = false;
    } else if (!isValidEmail(email.value)) {
        showError(email, 'Please enter a valid email address');
        isValid = false;
    }

    // Validate password
    if (password.value === '') {
        showError(password, 'Password is required');
        isValid = false;
    } else if (password.value.length < 6) {
        showError(password, 'Password must be at least 6 characters');
        isValid = false;
    }

    // Validate confirm password
    if (confirmPassword.value === '') {
        showError(confirmPassword, 'Please confirm your password');
        isValid = false;
    } else if (password.value !== confirmPassword.value) {
        showError(confirmPassword, 'Passwords do not match');
        isValid = false;
    }

    return isValid;
}

/**
 * Validate login form
 * @returns {boolean} True if form is valid, false otherwise
 */
function validateLoginForm() {
    let isValid = true;
    const username = document.getElementById('username');
    const password = document.getElementById('password');

    // Reset error messages
    resetErrors();

    // Validate username
    if (username.value.trim() === '') {
        showError(username, 'Username is required');
        isValid = false;
    }

    // Validate password
    if (password.value === '') {
        showError(password, 'Password is required');
        isValid = false;
    }

    return isValid;
}

/**
 * Validate artwork upload form
 * @returns {boolean} True if form is valid, false otherwise
 */
function validateUploadForm() {
    let isValid = true;
    const title = document.getElementById('title');
    const description = document.getElementById('description');
    const price = document.getElementById('price');
    const category = document.getElementById('category');
    const artworkImage = document.getElementById('artworkImage');

    // Reset error messages
    resetErrors();

    // Validate title
    if (title.value.trim() === '') {
        showError(title, 'Title is required');
        isValid = false;
    }

    // Validate description
    if (description.value.trim() === '') {
        showError(description, 'Description is required');
        isValid = false;
    }

    // Validate price
    if (price.value.trim() === '') {
        showError(price, 'Price is required');
        isValid = false;
    } else if (isNaN(price.value) || parseFloat(price.value) <= 0) {
        showError(price, 'Please enter a valid price');
        isValid = false;
    }

    // Validate category
    if (category.value === '') {
        showError(category, 'Please select a category');
        isValid = false;
    }

    // Validate image
    if (artworkImage.files.length === 0) {
        showError(artworkImage, 'Please select an image');
        isValid = false;
    } else {
        const file = artworkImage.files[0];
        const fileType = file.type;
        const validImageTypes = ['image/jpeg', 'image/png', 'image/gif'];

        if (!validImageTypes.includes(fileType)) {
            showError(artworkImage, 'Please select a valid image file (JPEG, PNG, GIF)');
            isValid = false;
        }
    }

    return isValid;
}

/**
 * Show error message for form field
 * @param {HTMLElement} input - The input element
 * @param {string} message - The error message
 */
function showError(input, message) {
    const formControl = input.parentElement;
    const errorElement = formControl.querySelector('.invalid-feedback') || document.createElement('div');

    errorElement.className = 'invalid-feedback';
    errorElement.innerText = message;

    if (!formControl.querySelector('.invalid-feedback')) {
        formControl.appendChild(errorElement);
    }

    input.classList.add('is-invalid');
}

/**
 * Reset all error messages
 */
function resetErrors() {
    const invalidInputs = document.querySelectorAll('.is-invalid');
    invalidInputs.forEach(input => {
        input.classList.remove('is-invalid');
    });
}

/**
 * Check if email is valid
 * @param {string} email - The email to validate
 * @returns {boolean} True if email is valid, false otherwise
 */
function isValidEmail(email) {
    const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
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
            // Update cart count
            const cartCount = document.getElementById('cart-count');
            if (cartCount) {
                cartCount.innerText = data.cartCount;
            }

            // Show success message
            showToast('Success', 'Artwork added to cart!', 'success');
        } else {
            showToast('Error', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'An error occurred. Please try again.', 'error');
    });
}

// Like artwork functionality is now handled in artwork.js

/**
 * Update cart item quantity
 * @param {number} cartItemId - The ID of the cart item
 * @param {number} quantity - The new quantity
 */
function updateCartQuantity(cartItemId, quantity) {
    fetch('/php/update_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'cart_item_id=' + cartItemId + '&quantity=' + quantity
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update subtotal
            const subtotal = document.getElementById('subtotal-' + cartItemId);
            if (subtotal) {
                subtotal.innerText = '$' + data.subtotal;
            }

            // Update total
            const total = document.getElementById('cart-total');
            if (total) {
                total.innerText = '$' + data.total;
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
 * Preview image before upload
 * @param {HTMLElement} input - The file input element
 * @param {HTMLElement} preview - The image preview element
 */
function previewImage(input, preview) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();

        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }

        reader.readAsDataURL(input.files[0]);
    }
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
