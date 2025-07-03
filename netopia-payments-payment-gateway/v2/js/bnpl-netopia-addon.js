/** To show Custom UI in Woocomerce Block */

function bindNetopiaRadios() {
  const radios = document.querySelectorAll('input[type="radio"][id^="netopia-method-"]');

  // Waiting for payment method radios to render...
  if (radios.length === 0) {
    return;
  }

  console.log(`Found ${radios.length} Netopia radios.`);

  function updateCollapse(selectedValue) {
    document.querySelectorAll('.netopia-collapse').forEach(div => {
      div.style.display = div.id === `collapse-${selectedValue}` ? 'block' : 'none';
    });

    // Inject dynamic content on selection for specific payment methods (Card, PayPo, Oney)
    if (selectedValue === 'bnpl.paypo') {
      
      const paypoDiv = document.getElementById('collapse-bnpl.paypo');
      if (paypoDiv) {
        paypoDiv.innerHTML = `
              <p>
                Cumpara acum, plateste in 30 de zile fara costuri suplimentare cu PayPo.
                <img src="${BNPLData.pluginUrl}/img/paypo.svg" alt="PayPo" style="display: inline; width: 95px; margin-bottom: -10px;">
              </p>
            
        `;
      }
    } else if(selectedValue === 'credit_card') {
      const cardDiv = document.getElementById('collapse-credit_card');
      if (cardDiv) {
        cardDiv.innerHTML = `<p>
                                Plata online prin NETOPIA Payments<br>
                                <img src="${BNPLData.pluginUrl}/img/netopia.svg" alt="PayPo" style="display: inline; width: 95px; margin-bottom: -30px;">
                              </p>`;
      }
    } else if(selectedValue == 'bnpl.oney') {
      const oneyDiv = document.getElementById('collapse-bnpl.oney');
      if (oneyDiv) {
        oneyDiv.innerHTML = document.getElementById('oney_ui_section').innerHTML;
        document.getElementById('oney_ui_section').style.display = 'block';
      }
    }
  }

  radios.forEach((radio) => {
    radio.addEventListener('change', function () {
      if (this.checked) {
        console.log(`Selected method: ${this.id}`);
        updateCollapse(this.value);
      }
    });

    if (radio.checked) {
      updateCollapse(radio.value);
    }
  });
}

// Poll for radios every 500ms max 10 sec
let tries = 0;
const interval = setInterval(() => {
  tries++;
  if (tries > 20) return clearInterval(interval);

  const radios = document.querySelectorAll('input[type="radio"][id^="netopia-method-"]');
  
  if (radios.length > 0) {
    clearInterval(interval);
    bindNetopiaRadios();
  }
}, 500);


/**
 * Initializes a listener on the main payment container.
 */
function initializePaymentListener() {
  // From your HTML, this is the stable container holding all payment methods.
  const paymentContainer = document.querySelector('.wp-block-woocommerce-checkout-payment-block');

  if (!paymentContainer) {
    // If the container isn't ready, retry after a moment.
    setTimeout(initializePaymentListener, 500);
    return;
  }

  // Add listener to the parent container. for when netopia payment method is selected.
  paymentContainer.addEventListener('change', function(event) {
    // Check if the element that was changed is a radio button for a payment method
    if (event.target.name === 'radio-control-wc-payment-method-options') {
      
      // Check if the selected payment method is Netopia
      if (event.target.value === 'netopiapayments') {
        console.log('NETOPIA Payments selected. Running bindNetopiaRadios...');
        // Run the function to set up the sub-options. Netopia Custom UI
        bindNetopiaRadios();
      }
    }
  });

  // Finally, check if Netopia is already selected when the page first loads.
  const initialNetopiaRadio = document.querySelector('#radio-control-wc-payment-method-options-netopiapayments');
  if (initialNetopiaRadio && initialNetopiaRadio.checked) {
      console.log('NETOPIA Payments is already selected on page load.');
      bindNetopiaRadios();
  }
}

// When the webpage is fully loaded, start the listener.
document.addEventListener('DOMContentLoaded', initializePaymentListener);
