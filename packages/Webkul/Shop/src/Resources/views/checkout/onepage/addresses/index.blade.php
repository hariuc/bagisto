{!! view_render_event('bagisto.shop.checkout.onepage.addresses.before') !!}

<v-checkout-addresses :cart="cart"></v-checkout-addresses>

{!! view_render_event('bagisto.shop.checkout.onepage.addresses.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-checkout-addresses-template"
    >
        <template v-if="isAddressLoading">
            <!-- Onepage Shimmer Effect --> 
            <x-shop::shimmer.checkout.onepage.address />
        </template>
        
        <template v-else>
            <div class="mt-8 mb-7">
                {!! view_render_event('bagisto.shop.checkout.onepage.addresses.billing.before') !!} 

                @include('shop::checkout.onepage.addresses.billing')

                {!! view_render_event('bagisto.shop.checkout.onepage.addresses.billing.after') !!}

                {!! view_render_event('bagisto.shop.checkout.onepage.addresses.shipping.before') !!}

                @include('shop::checkout.onepage.addresses.shipping')

                {!! view_render_event('bagisto.shop.checkout.onepage.addresses.shipping.after') !!} 
            </div>
        </template>
    </script>

    <script type="module">
        app.component('v-checkout-addresses', {
            template: '#v-checkout-addresses-template',

            props: ['cart'],

            data() {
                return {        
                    addNewBillingAddress: false,

                    countries: [],

                    customer: @json(auth()->guard('customer')->user()),

                    customerAddresses: {
                        billing: [],

                        shipping: [],
                    },

                    isLoading: false,

                    isAddressLoading: true,

                    selectedBillingAddressId: null,

                    selectedShippingAddressId: null,

                    shippingIsSameAsBilling: true,

                    toggleShippingForm: false,

                    tempBillingAddress: {},

                    isAddressEditable: false,
                };
            },

            mounted() {                
                this.get();

                this.getCountries();
            },

            watch: {
                selectedAddresses: {
                    handler(newQuestion) {
                        this.resetState();
                    },
                    
                    deep: true
                },

                selectedBillingAddressId: {
                    handler() {
                        this.resetState();
                    },

                    deep: true,
                },

                selectedShippingAddressId: {
                    handler() {
                        this.resetState();
                    },

                    deep: true,
                }
            },
            
            computed: {
                savedBillingAddresses() {
                    const addresses = [];

                    this.customerAddresses.billing.forEach((address) => addresses.push(address));

                    return addresses;
                },

                savedShippingAddresses() {
                    const addresses = [];

                    this.customerAddresses.shipping.forEach((address) => addresses.push(address));

                    return addresses;
                },
            },

            methods: {
                get() {
                    this.isAddressLoading = true;

                    if (! this.customer) {
                        this.isAddressLoading = false;

                        return;
                    }

                    this.$axios.get('{{ route('api.shop.customers.account.addresses.index') }}')
                        .then(response => {
                            this.customerAddresses.billing = this.customerAddresses.shipping = response.data.data;

                            this.isAddressLoading = false;
                        })
                        .catch(() => {});
                },

                store(params, { resetForm }) {
                    this.isLoading = true;

                    if (params[params.type].id) {
                        return this.update(params);
                    }

                    if (! this.customer
                        || (
                            this.customer
                            && ! params[params.type].save_address
                        )
                    ) {       
                        this.customerAddresses[params.type].push(params[params.type]);

                        this.addNewBillingAddress = false;

                        this.toggleShippingForm = false;

                        this.isLoading = false;

                        this.resetState();

                        return;
                    }

                    this.$axios.post('{{ route('api.shop.customers.account.addresses.store') }}', params[params.type])
                        .then(() => {
                            this.get();

                            this.$emitter.emit('update-cart-summary');

                            this.addNewBillingAddress = false;

                            this.toggleShippingForm = false;

                            this.isLoading = false;

                            this.resetState();

                            resetForm();
                        })
                        .catch(() => {});
                },

                update(params) {
                    this.isLoading = true;

                    if (! this.customer) {
                        const existingAddressIndex = this.customerAddresses[params.type].findIndex(address => address.id === params[params.type].id);

                        if (existingAddressIndex !== -1) {
                            this.customerAddresses[params.type][existingAddressIndex] = {
                                ...this.customerAddresses[params.type][existingAddressIndex],
                                ...params[params.type]
                            };
                        }

                        this.addNewBillingAddress = false;

                        this.toggleShippingForm = false;

                        this.isAddressEditable = false;

                        this.isLoading = false;

                        return;
                    }

                    this.$axios.post("{{ route('api.shop.customers.account.addresses.update') }}", params[params.type])
                        .then(response => {
                            this.get();

                            this.$emitter.emit('update-cart-summary');

                            this.addNewBillingAddress = false;

                            this.toggleShippingForm = false;

                            this.isAddressEditable = false;

                            this.isLoading = false;

                            this.resetState();

                            resetForm();
                        })
                        .catch(() => {});
                },

                proceed() {
                    this.isLoading = true;

                    let params = {
                        billing: {
                            address1: [''],

                            address_id: this.selectedBillingAddressId,
                        },

                        shipping: {
                            address1: [''],

                            address_id: this.selectedShippingAddressId,
                        }
                    };

                    if (! Array.isArray(params.billing?.address1)) {
                        params.billing = Object.assign({}, params.billing);

                        params.billing.address1 = params.billing.address1.split('\n');
                    }

                    if (! Array.isArray(params.shipping?.address1)) {
                        params.shipping = Object.assign({}, params.shipping);

                        params.shipping.address1 = params.shipping.address1.split('\n');
                    }

                    this.$axios.post('{{ route('shop.checkout.onepage.addresses.store') }}', params)
                        .then((response) => {
                            if (response.data.data.shippingMethods) {
                                this.$emitter.emit('shipping-methods', response.data.data.shippingMethods);

                                this.$emitter.emit('is-show-shipping-methods', true);

                                this.$emitter.emit('is-shipping-loading', false);
                            }

                            if (response.data.data.payment_methods) {
                                this.$emitter.emit('payment-methods', response.data.data.payment_methods);

                                this.$emitter.emit('is-show-payment-methods', true);

                                this.$emitter.emit('is-payment-loading', false);

                                this.isLoading = false;
                            }

                            this.isLoading = false;

                            this.$emitter.emit('update-cart-summary');

                            resetForm();
                        })
                        .catch(() => {});
                },

                haveStates(addressType) {
                    return false;
                },

                getCountries() {
                    this.$axios.get("{{ route('shop.api.core.countries') }}")
                        .then(response => {
                            this.countries = response.data.data;
                        })
                        .catch(() => {});
                },

                resetState(state = false) {
                    this.$emitter.emit('is-show-shipping-methods', state);

                    this.$emitter.emit('is-show-payment-methods', false);
                },
            },
        });
    </script>
@endPushOnce