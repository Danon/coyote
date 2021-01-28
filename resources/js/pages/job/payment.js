import Vue from 'vue';
import VueMaskedInput from 'vue-masked-input';
import VueFormGroup from '@/components/forms/form-group.vue';
import VueText from '@/components/forms/text.vue';
import VueSelect from '@/components/forms/select.vue';
import VueCheckbox from '@/components/forms/checkbox.vue';
import VueButton from '@/components/forms/button.vue';
import axios from 'axios';
import VueNotifications from "vue-notification";
import {default as axiosErrorHandler} from "@/libs/axios-error-handler";

Vue.use(VueNotifications, {componentName: 'vue-notifications'});

axiosErrorHandler(message => Vue.notify({type: 'error', text: message}));

const DEFAULT_VAT_RATE = 1.23;

new Vue({
  el: '#js-payment',
  delimiters: ['${', '}'],
  components: {
    'vue-form-group': VueFormGroup,
    'vue-text': VueText,
    'vue-select': VueSelect,
    'vue-checkbox': VueCheckbox,
    'vue-masked-input': VueMaskedInput,
    'vue-button': VueButton
  },
  filters: {
    money(value) {
      return parseFloat(value).toFixed(2);
    }
  },
  data: {
    countries: window.countries,
    netPrice: window.netPrice,
    vatRate: window.vatRate,
    vatRates: window.vatRates,
    form: window.form,
    banks: window.banks,
    coupon: {
      code: null,
      amount: 0
    },
    enableInvoice: true,
    isCoupon: false,
    errors: {},
    isProcessing: false
  },
  mounted() {
    this.stripe = Stripe(window.stripeKey);
    const elements = this.stripe.elements();

    const style = {
      iconStyle: 'solid'
    };

    this.card = elements.create('card', { style });
    this.card.mount('#card-element');
  },
  methods: {
    calculate() {
      // if VAT ID is empty we must add VAT
      this.vatRate = this.enableInvoice ? this.vatRates[this.form.invoice.country_id] : DEFAULT_VAT_RATE;
    },

    cardPayment({ token, success_url }) {
      this.stripe.confirmCardPayment(token, {
        payment_method: {
          card: this.card,
          billing_details: {
            name: this.form.invoice.name
          }
        }
      }).then(result => {
        if (result.error) {
          this.$notify({type: 'error', text: result.error.message});
        } else {
          // The payment has been processed!
          if (result.paymentIntent.status === 'succeeded') {
            window.location.href = success_url;
          }
        }
      });
    },

    async p24Payment({ token, success_url }) {
      const { error } = await this.stripe.confirmP24Payment(
        token,
        {
          payment_method: {
            billing_details: {
              email: this.form.invoice.email
            }
          },
          return_url: success_url,
        }
      );

      if (error) {
        this.$notify({type: 'error', text: error});
      }
    },

    makePayment() {
      const data = Object.assign(this.form, {price: this.grossPrice, enable_invoice: this.grossPrice > 0 ? this.enableInvoice : false});

      this.errors = {};
      this.isProcessing = true;

      axios.post(window.location.href, data)
        .then(response => {
          if (response.status === 201) {
            window.location.href = response.data;
          }

          this[`${this.form.payment_method}Payment`](response.data)
        })
        .catch(err => {
          console.error(err);

          if (err.response.status !== 422) {
            return;
          }

          this.errors = err.response.data.errors;
        })
        .finally(() => this.isProcessing = false)
    },

    setPaymentMethod(method) {
      this.form.payment_method = method;
    }
  },
  computed: {
    percentageVatRate() {
      return (this.vatRate * 100) - 100;
    },

    grossPrice() {
      return this.discountNetPrice * this.vatRate;
    },

    discountNetPrice() {
      return Math.max(0, this.netPrice - this.coupon.amount);
    },

    vatPrice() {
      return this.grossPrice - this.discountNetPrice;
    }
  },
  watch: {
    'coupon.code': function(newValue) {
      axios.get('/Praca/Coupon/Validate', {params: {code: newValue}}).then(result => {
        this.coupon.amount = result.data;
        this.form.coupon = newValue;
      });
    },

    'form.invoice.country_id': function() {
      this.calculate();
    },

    'enableInvoice': function() {
      this.calculate();
    }
  }
});
