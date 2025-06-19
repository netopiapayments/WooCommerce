document.addEventListener('DOMContentLoaded', (event) => {
    const multiSelect = document.getElementById('woocommerce_netopiapayments_payment_methods');
    const agreeCheckbox = document.getElementById('woocommerce_netopiapayments_agreement');
    const createPageButton = document.getElementById('agreeToCreateOneyPage'); 

    multiSelect.addEventListener('change', (event) => {
        const selectedOptions = Array.from(multiSelect.selectedOptions).map(option => option.value);
        
        const optionToMirror = 'oney';  // The option that triggers the mirror action
        const mirroredOption = 'credit_card';  // The option that gets mirrored
        
        if (selectedOptions.includes(optionToMirror)) {
            if (!selectedOptions.includes(mirroredOption)) {
                multiSelect.querySelector(`option[value="${mirroredOption}"]`).selected = true;
                toastr.success('Credit card has been automatically selected because you selected Oney option.', 'success!');
            }
        }
    });

    // Add listener for the "Create Page" button
    createPageButton.addEventListener('click', () => {
    const selectedOptions = Array.from(multiSelect.selectedOptions).map(option => option.value);
    const optionToMirror = 'oney';  // The option to check

        if (selectedOptions.includes(optionToMirror)) {
            if (agreeCheckbox.checked) {
                // Trigger Ajax to create the page
                jQuery.ajax({
                    url: oneyNetopia.ajaxUrl, // Ajax URL passed from PHP
                    type: 'POST',
                    data: {
                        action: 'create_oney_netopia_page', // Action name
                        security: oneyNetopia.nonce, // Nonce for security
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.data, 'Success!');
                        } else {
                            toastr.error('Failed to create the "Oferta Rate Oney" page.', 'Error!');
                        }
                    },
                    error: function() {
                        toastr.error('An error occurred while creating the "Oferta Rate Oney" page.', 'Error!');
                    }
                });
            }else {
                toastr.error('You must agree to the terms to create the "Oferta Rate Oney" page.', 'Error!');
            }
        } else {
            toastr.error('You must select the "Oney" option to create the page.', 'Error!');
        }
    });
});