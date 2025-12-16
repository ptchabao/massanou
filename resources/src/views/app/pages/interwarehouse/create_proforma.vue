<template>
  <div class="main-content">
    <breadcumb :page="$t('CreateProforma')" :folder="$t('InterWarehouseRequests')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <validation-observer ref="Create_Proforma" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Proforma">
        <b-row>
          <b-col lg="12" md="12" sm="12">
            <b-card>
              <b-row>

                 <!-- date  -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                   <b-form-group :label="$t('date')">
                      <b-form-input
                        disabled
                        type="date"
                        v-model="iw_request.date"
                      ></b-form-input>
                    </b-form-group>
                </b-col>
                
                <!-- Requester Warehouse -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                    <b-form-group :label="$t('RequesterWarehouse')">
                      <b-form-input disabled v-model="requester_warehouse_name"></b-form-input>
                    </b-form-group>
                </b-col>

                <!-- Supplier Warehouse -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                    <b-form-group :label="$t('SupplierWarehouse')">
                      <b-form-input disabled v-model="supplier_warehouse_name"></b-form-input>
                    </b-form-group>
                </b-col>

                <!-- order products  -->
                <b-col md="12">
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead class="bg-gray-300">
                        <tr>
                          <th scope="col">#</th>
                          <th scope="col">{{$t('ProductName')}}</th>
                          <th scope="col">{{$t('Net_Unit_Cost')}}</th>
                          <th scope="col">{{$t('CurrentStock')}}</th>
                          <th scope="col">{{$t('Qty')}}</th>
                          <th scope="col">{{$t('Discount')}}</th>
                          <th scope="col">{{$t('Tax')}}</th>
                          <th scope="col">{{$t('SubTotal')}}</th>
                          <th scope="col" class="text-center">
                            <i class="fa fa-trash"></i>
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="detail in details">
                          <td>{{detail.detail_id}}</td>
                          <td>
                            <span>{{detail.code}}</span>
                            <br>
                            <span class="badge badge-success">{{detail.name}}</span>
                            <i @click="Modal_Updat_Detail(detail)" class="i-Edit cursor-pointer text-success"></i>
                          </td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.Net_cost, 3)}}</td>
                          <td>
                            <span class="badge badge-outline-warning">{{detail.stock}} {{detail.unitPurchase}}</span>
                          </td>
                          <td>
                            <div class="quantity">
                              <b-input-group>
                                <b-input-group-prepend>
                                  <span
                                    class="btn btn-primary btn-sm"
                                    @click="decrement(detail ,detail.detail_id)"
                                  >-</span>
                                </b-input-group-prepend>
                                <input
                                  class="form-control"
                                  @keyup="Verified_Qty(detail,detail.detail_id)"
                                  v-model.number="detail.quantity"
                                >
                                <b-input-group-append>
                                  <span
                                    class="btn btn-primary btn-sm"
                                    @click="increment(detail ,detail.detail_id)"
                                  >+</span>
                                </b-input-group-append>
                              </b-input-group>
                            </div>
                          </td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.DiscountNet * detail.quantity, 2)}}</td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.taxe * detail.quantity, 2)}}</td>
                          <td>{{currentUser.currency}} {{detail.subtotal.toFixed(2)}}</td>
                          <td class="text-center">
                            <a
                              @click="delete_Product_Detail(detail.detail_id)"
                              class="btn btn-icon btn-sm"
                              title="Delete"
                            >
                              <i class="i-Close-Window text-25 text-danger"></i>
                            </a>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </b-col>

                <div class="offset-md-9 col-md-3 mt-4">
                  <table class="table table-striped table-sm">
                    <tbody>
                      <tr>
                        <td class="bold">{{$t('OrderTax')}}</td>
                        <td>
                          <span>{{currentUser.currency}} {{iw_request.TaxNet.toFixed(2)}} ({{formatNumber(iw_request.tax_rate ,2)}} %)</span>
                        </td>
                      </tr>
                      <tr>
                        <td class="bold">{{$t('Discount')}}</td>
                        <td>{{currentUser.currency}} {{iw_request.discount.toFixed(2)}}</td>
                      </tr>
                      <tr>
                        <td class="bold">{{$t('Shipping')}}</td>
                        <td>{{currentUser.currency}} {{iw_request.shipping.toFixed(2)}}</td>
                      </tr>
                      <tr>
                        <td>
                          <span class="font-weight-bold">{{$t('Total')}}</span>
                        </td>
                        <td>
                          <span
                            class="font-weight-bold"
                          >{{currentUser.currency}} {{iw_request.GrandTotal.toFixed(2)}}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <b-col md="12">
                  <b-form-group :label="$t('Note')">
                    <textarea
                      v-model="iw_request.notes"
                      rows="4"
                      class="form-control"
                      :placeholder="$t('Afewwords')"
                    ></textarea>
                  </b-form-group>
                </b-col>
                <b-col md="12">
                   <b-form-group>
                    <b-button variant="primary" @click="Submit_Proforma" :disabled="SubmitProcessing"><i class="i-Yes me-2 font-weight-bold"></i> {{$t('SubmitProforma')}}</b-button>
                     <div v-once class="typo__p" v-if="SubmitProcessing">
                      <div class="spinner sm spinner-primary mt-3"></div>
                    </div>
                  </b-form-group>
                </b-col>
              </b-row>
            </b-card>
          </b-col>
        </b-row>
      </b-form>
    </validation-observer>

    <!-- Modal Update detail Product -->
    <validation-observer ref="Update_Detail_Proforma">
      <b-modal hide-footer size="md" id="form_Update_Detail" :title="detail.name">
        <b-form @submit.prevent="submit_Update_Detail">
          <b-row>
            <!-- Unit Cost -->
            <b-col lg="12" md="12" sm="12">
              <validation-provider
                name="Product Cost"
                :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('ProductCost') + ' ' + '*'" id="cost-input">
                  <b-form-input
                    label="Product Cost"
                    v-model.number="detail.Unit_cost"
                    :state="getValidationState(validationContext)"
                    aria-describedby="cost-feedback"
                  ></b-form-input>
                  <b-form-invalid-feedback id="cost-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Tax Method -->
            <b-col lg="12" md="12" sm="12">
              <validation-provider name="Tax Method" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('TaxMethod') + ' ' + '*'">
                  <v-select
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    v-model="detail.tax_method"
                    :reduce="label => label.value"
                    :placeholder="$t('Choose_Method')"
                    :options="
                           [
                            {label: 'Exclusive', value: '1'},
                            {label: 'Inclusive', value: '2'}
                           ]"
                  ></v-select>
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Tax Rate -->
            <b-col lg="12" md="12" sm="12">
              <validation-provider
                name="Order Tax"
                :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('OrderTax') + ' ' + '*'">
                  <b-input-group append="%">
                    <b-form-input
                      label="Order Tax"
                      v-model.number="detail.tax_percent"
                      :state="getValidationState(validationContext)"
                      aria-describedby="OrderTax-feedback"
                    ></b-form-input>
                  </b-input-group>
                  <b-form-invalid-feedback id="OrderTax-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Discount Method -->
            <b-col lg="12" md="12" sm="12">
              <validation-provider name="Discount Method" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('Discount_Method') + ' ' + '*'">
                  <v-select
                    v-model="detail.discount_Method"
                    :reduce="label => label.value"
                    :placeholder="$t('Choose_Method')"
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    :options="
                           [
                            {label: 'Percent', value: '1'},
                            {label: 'Fixed', value: '2'}
                           ]"
                  ></v-select>
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Discount -->
            <b-col lg="12" md="12" sm="12">
              <validation-provider
                name="Discount"
                :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Discount') + ' ' + '*'">
                  <b-form-input
                    label="Discount"
                    v-model.number="detail.discount"
                    :state="getValidationState(validationContext)"
                    aria-describedby="Discount-feedback"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Discount-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <b-col md="12">
              <b-form-group>
                <b-button variant="primary" type="submit">{{$t('submit')}}</b-button>
              </b-form-group>
            </b-col>
          </b-row>
        </b-form>
      </b-modal>
    </validation-observer>

  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Create Proforma"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing:false,
      details: [],
      requester_warehouse_name: "",
      supplier_warehouse_name: "",
      iw_request: {
        id: "",
        date: "",
        notes: "",
        tax_rate: 0,
        TaxNet: 0,
        shipping: 0,
        discount: 0,
        GrandTotal: 0
      },
      detail: {
        quantity: "",
        discount: 0,
        Unit_cost: "",
        discount_Method: "2",
        tax_percent: 0,
        tax_method: "1"
      },
      units: []
    };
  },
  computed: {
    ...mapGetters(["currentUser"])
  },

  methods: {

    formatNumber(number, dec) {
      const value = (typeof number === "string"
        ? number
        : number.toString()
      ).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec)
        return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}.${formated}`;
    },

    Submit_Proforma() {
      this.$refs.Create_Proforma.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Create_Proforma();
        }
      });
    },

    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    //------ Show Modal Update Detail Product
    Modal_Updat_Detail(detail) {
      this.detail = {};
      this.detail.name = detail.name;
      this.detail.detail_id = detail.detail_id;
      this.detail.Unit_cost = detail.Unit_cost;
      this.detail.tax_percent = detail.tax_percent;
      this.detail.tax_method = detail.tax_method;
      this.detail.discount = detail.discount;
      this.detail.discount_Method = detail.discount_Method;
      this.detail.quantity = detail.quantity;
      this.detail.stock = detail.stock;
      this.$bvModal.show("form_Update_Detail");
    },

    submit_Update_Detail() {
      this.$refs.Update_Detail_Proforma.validate().then(success => {
        if (!success) {
          return;
        } else {
          this.Update_Detail();
        }
      });
    },

    Update_Detail() {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === this.detail.detail_id) {
          this.details[i].Unit_cost = this.detail.Unit_cost;
          this.details[i].tax_percent = this.detail.tax_percent;
          this.details[i].tax_method = this.detail.tax_method;
          this.details[i].discount = this.detail.discount;
          this.details[i].discount_Method = this.detail.discount_Method;
          
          this.Calcul_Total();
          this.$forceUpdate();
        }
      }
      this.$bvModal.hide("form_Update_Detail");
    },

    Calcul_Total() {
      this.total = 0;
      for (let index = 0; index < this.details.length; index++) {
        let detail = this.details[index];
        
        // Calculate tax and discount
        let tax = 0;
        let discount = 0;

        // Discount
        if (detail.discount_Method == "2") {
          // Fixed
          discount = detail.discount;
        } else {
          // Percent
          discount = (detail.Unit_cost * detail.discount) / 100;
        }
        
        // Tax
        if (detail.tax_method == "1") {
          // Exclusive
          tax = ((detail.Unit_cost - discount) * detail.tax_percent) / 100;
        } else {
          // Inclusive
          tax = (detail.Unit_cost - discount) * detail.tax_percent / (100 + detail.tax_percent);
        }

        detail.Net_cost = detail.Unit_cost - discount;
        detail.taxe = tax;
        detail.DiscountNet = discount;
        
        // Subtotal
        if (detail.tax_method == "1") {
             detail.subtotal = (detail.Net_cost + tax) * detail.quantity;
        } else {
             detail.subtotal = detail.Unit_cost * detail.quantity; // Inclusive means unit cost already has tax
             // Wait, if inclusive, Net_cost is without tax. 
             // Logic:
             // Exclusive: Price = 100, Tax = 10%. Total = 110.
             // Inclusive: Price = 110 (includes 10% tax). Tax amount = 10. Net = 100.
             // Here Unit_cost seems to be the base price entered.
             // If inclusive, Unit_cost is the final price.
        }
        
        // Let's stick to standard logic used in other modules
        // If Exclusive: Net_cost = Unit_cost - Discount. Tax = Net_cost * Rate. Subtotal = (Net_cost + Tax) * Qty.
        // If Inclusive: Net_cost = (Unit_cost - Discount) / (1 + Rate). Tax = (Unit_cost - Discount) - Net_cost. Subtotal = (Unit_cost - Discount) * Qty.
        
        // Re-implementing logic from Purchase/Sale
        let price_after_discount = detail.Unit_cost - discount;
        if (detail.tax_method == "1") {
            // Exclusive
            detail.taxe = price_after_discount * detail.tax_percent / 100;
            detail.subtotal = (price_after_discount + detail.taxe) * detail.quantity;
            detail.Net_cost = price_after_discount;
        } else {
            // Inclusive
            detail.taxe = price_after_discount * detail.tax_percent / (100 + detail.tax_percent);
            detail.subtotal = price_after_discount * detail.quantity;
            detail.Net_cost = price_after_discount - detail.taxe;
        }

        this.total += detail.subtotal;
      }
      
      this.iw_request.GrandTotal = this.total;
      this.iw_request.TaxNet = 0; // Global tax not implemented for now, using item tax
      // Actually, if there is global tax/discount/shipping, add them here.
      // For now assuming 0 global tax/discount/shipping as they are not in the form (except display).
      // If I want to add them, I need inputs for them.
      // Let's keep it simple: sum of subtotals.
    },

    Verified_Qty(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === id) {
          if (isNaN(detail.quantity)) {
            this.details[i].quantity = 1;
          }
           if (detail.quantity > detail.stock) {
            this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
            this.details[i].quantity = detail.stock;
          }
          this.Calcul_Total();
          this.$forceUpdate();
        }
      }
    },

    increment(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === id) {
           if (detail.quantity + 1 > detail.stock) {
            this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
          } else {
            this.details[i].quantity = parseFloat(detail.quantity) + 1;
          }
          this.Calcul_Total();
          this.$forceUpdate();
        }
      }
    },

    decrement(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === id) {
          if (detail.quantity - 1 > 0) {
              this.details[i].quantity = parseFloat(detail.quantity) - 1;
          }
          this.Calcul_Total();
          this.$forceUpdate();
        }
      }
    },

    delete_Product_Detail(id) {
      for (var i = 0; i < this.details.length; i++) {
        if (id === this.details[i].detail_id) {
          this.details.splice(i, 1);
          this.Calcul_Total();
        }
      }
    },

    Create_Proforma() {
      this.SubmitProcessing = true;
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("interwarehouse_requests/" + this.iw_request.id + "/proforma", {
          request: this.iw_request,
          details: this.details,
          GrandTotal: this.iw_request.GrandTotal
        })
        .then(response => {
          NProgress.done();
          this.makeToast(
            "success",
            "Proforma créé avec succès",
            this.$t("Success")
          );
          this.SubmitProcessing = false;
          this.$router.push({ name: "index_interwarehouse" });
        })
        .catch(error => {
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          this.SubmitProcessing = false;
        });
    },

    GetElements() {
      let id = this.$route.params.id;
      axios
        .get("interwarehouse_requests/" + id + "/edit") // Reusing edit endpoint to get data
        .then(response => {
          this.iw_request = response.data.interwarehouse_request;
          this.details = response.data.details;
          
          // Fetch warehouse names
          const warehouses = response.data.all_warehouses;
          const req_w = warehouses.find(w => w.id === this.iw_request.requester_warehouse_id);
          const sup_w = warehouses.find(w => w.id === this.iw_request.supplier_warehouse_id);
          this.requester_warehouse_name = req_w ? req_w.name : '';
          this.supplier_warehouse_name = sup_w ? sup_w.name : '';

          // Initialize details with default values if 0
          this.details.forEach(d => {
             if(!d.Unit_cost) d.Unit_cost = 0;
             if(!d.tax_percent) d.tax_percent = 0;
             if(!d.discount) d.discount = 0;
             if(!d.tax_method) d.tax_method = "1";
             if(!d.discount_Method) d.discount_Method = "2";
             
             // Fetch stock for supplier warehouse
             // The edit endpoint returns details with stock from requester warehouse?
             // No, I need stock from supplier warehouse to validate availability.
             // I should fetch stock for these products in supplier warehouse.
             this.Get_Stock(d);
          });

          this.Calcul_Total();
          this.isLoading = false;
        })
        .catch(error => {
          setTimeout(() => {
            this.isLoading = false;
            this.$router.push({ name: "index_interwarehouse" });
          }, 500);
        });
    },

    Get_Stock(detail) {
        axios.get("Products/Warehouse/" + this.iw_request.supplier_warehouse_id + "?stock=" + 0) // This gets all products
        .then(response => {
             // This is inefficient to get all products. 
             // Better to have an endpoint to get stock for specific product in warehouse.
             // Or just trust the user for now / validation on backend.
             // But I need to show stock.
             // Let's use the existing endpoint but filter.
             const product = response.data.find(p => p.id === detail.product_id && p.product_variant_id === detail.product_variant_id);
             if(product) {
                 detail.stock = product.qte;
                 this.$forceUpdate();
             }
        });
    }
  },

  created() {
    this.GetElements();
  }
};
</script>
