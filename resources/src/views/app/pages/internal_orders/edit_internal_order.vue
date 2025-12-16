<template>
  <div class="main-content">
    <breadcumb :page="$t('Edit_InternalOrder')" :folder="$t('ListInternalOrders')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <validation-observer ref="Edit_InternalOrder" v-if="!isLoading">
      <b-form @submit.prevent="Submit_InternalOrder">
        <b-row>
          <b-col lg="12" md="12" sm="12">
            <b-card>
              <b-row>

                <b-modal hide-footer id="open_scan" size="md" title="Barcode Scanner">
                  <qrcode-scanner
                    :qrbox="250" 
                    :fps="10" 
                    style="width: 100%; height: calc(100vh - 56px);"
                    @result="onScan"
                  />
                </b-modal>

                 <!-- date  -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider
                    name="date"
                    :rules="{ required: true}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('date') + ' ' + '*'">
                      <b-form-input
                        :state="getValidationState(validationContext)"
                        aria-describedby="date-feedback"
                        type="date"
                        v-model="internal_order.date"
                      ></b-form-input>
                      <b-form-invalid-feedback
                        id="OrderTax-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>
                <!-- From warehouse (Requester) -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider name="From Warehouse" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('FromWarehouse') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        :disabled="details.length > 0"
                        @input="Selected_From_Warehouse"
                        v-model="internal_order.from_warehouse"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Warehouse')"
                        :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- To warehouse (Supplier) -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider name="To Warehouse" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('ToWarehouse') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        v-model="internal_order.to_warehouse"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Warehouse')"
                        :options="to_warehouses.map(to_warehouses => ({label: to_warehouses.name, value: to_warehouses.id}))"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                 <!-- Product -->
                 <b-col md="12" class="mb-5">
                  <h6>{{$t('ProductName')}}</h6>
                 
                  <div id="autocomplete" class="autocomplete">
                    <div class="input-with-icon">
                      <img src="/assets_setup/scan.png" alt="Scan" class="scan-icon" @click="showModal">
                    <input 
                     :placeholder="$t('Scan_Search_Product_by_Code_Name')"
                       @input='e => search_input = e.target.value' 
                      @keyup="search(search_input)"
                      @focus="handleFocus"
                      @blur="handleBlur"
                      ref="product_autocomplete"
                      class="autocomplete-input" />
                    </div>
                    <ul class="autocomplete-result-list" v-show="focused">
                      <li class="autocomplete-result" v-for="product_fil in product_filter" @mousedown="SearchProduct(product_fil)">{{getResultValue(product_fil)}}</li>
                    </ul>
                </div>
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
                          <th scope="col">{{$t('SubTotal')}}</th>
                          <th scope="col" class="text-center">
                            <i class="fa fa-trash"></i>
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-if="details.length <=0">
                          <td colspan="7">{{$t('NodataAvailable')}}</td>
                        </tr>
                        <tr v-for="detail in details">
                          <td>{{detail.detail_id}}</td>
                          <td>
                            <span>{{detail.code}}</span>
                            <br>
                            <span class="badge badge-success">{{detail.name}}</span>
                            <i @click="Modal_Updat_Detail(detail)" class="i-Edit"></i>
                          </td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.Net_cost, 3)}}</td>
                          <td>
                            <span
                              class="badge badge-outline-warning"
                            >{{detail.stock}} {{detail.unitPurchase}}</span>
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
                           <td>{{currentUser.currency}} {{detail.subtotal.toFixed(2)}}</td>
                          <td>
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
                        <td>
                          <span class="font-weight-bold">{{$t('Total')}}</span>
                        </td>
                        <td>
                          <span
                            class="font-weight-bold"
                          >{{currentUser.currency}} {{GrandTotal.toFixed(2)}}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <b-col md="12">
                  <b-form-group :label="$t('Note')">
                    <textarea
                      v-model="internal_order.notes"
                      rows="4"
                      class="form-control"
                      :placeholder="$t('Afewwords')"
                    ></textarea>
                  </b-form-group>
                </b-col>
                <b-col md="12">
                  <b-form-group>
                    <b-button variant="primary" @click="Submit_InternalOrder" :disabled="SubmitProcessing"><i class="i-Yes me-2 font-weight-bold"></i> {{$t('submit')}}</b-button>
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
    <validation-observer ref="Update_Detail_InternalOrder">
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

             <!-- Unit Purchase -->
            <b-col lg="12" md="12" sm="12">
              <validation-provider name="Unit Purchase" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('UnitPurchase') + ' ' + '*'">
                  <v-select
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    v-model="detail.purchase_unit_id"
                    :placeholder="$t('Choose_Unit_Purchase')"
                    :reduce="label => label.value"
                    :options="units.map(units => ({label: units.name, value: units.id}))"
                  />
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
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
    title: "Edit Internal Order"
  },
  data() {
    return {
      focused: false,
      timer:null,
      search_input:'',
      product_filter:[],
      isLoading: true,
      SubmitProcessing:false,
      details: [],
      detail: {
        quantity: "",
        discount: 0,
        Unit_cost: "",
        discount_Method: "2",
        tax_percent: 0,
        tax_method: "1"
      },
      warehouses: [],
      to_warehouses: [],
      products: [],
      units: [],
      symbol: "",
      internal_order: {
        id: "",
        from_warehouse: "",
        to_warehouse: "",
        statut: "pending",
        notes: "",
        date: "",
        items: 0,
        tax_rate: 0,
        TaxNet: 0,
        shipping: 0,
        discount: 0
      },
      total: 0,
      GrandTotal: 0,
      product: {
        id: "",
        code: "",
        stock: "",
        quantity: 1,
        discount: 0,
        DiscountNet: 0,
        discount_Method: "2",
        name: "",
        unitPurchase: "",
        purchase_unit_id:"",
        fix_stock:"",
        fix_cost:"",
        Net_cost: "",
        Unit_cost: "",
        Total_cost: "",
        subtotal: "",
        product_id: "",
        detail_id: "",
        taxe: 0,
        tax_percent: 0,
        tax_method: "1",
        product_variant_id: ""
      }
    };
  },
  computed: {
    ...mapGetters(["currentUser"])
  },

  methods: {

     handleFocus() {
      this.focused = true
    },

    handleBlur() {
      this.focused = false
    },

    showModal() {
      this.$bvModal.show('open_scan');
      
    },

    onScan (decodedText, decodedResult) {
      const code = decodedText;
      this.search_input = code;
      this.search();
      this.$bvModal.hide('open_scan');
    },

    
    //------------- Submit Validation Update Internal Order
    Submit_InternalOrder() {
      this.$refs.Edit_InternalOrder.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Update_InternalOrder();
        }
      });
    },

    //---Submit Validation Update Detail
    submit_Update_Detail() {
      this.$refs.Update_Detail_InternalOrder.validate().then(success => {
        if (!success) {
          return;
        } else {
          this.Update_Detail();
        }
      });
    },

     //---------------------- get_units ------------------------------\\
    get_units(value) {
      axios
        .get("get_units?id=" + value)
        .then(({ data }) => (this.units = data));
    },

    //------ Show Modal Update Detail Product
    Modal_Updat_Detail(detail) {
      this.detail = {};
      this.detail.name = detail.name;
      this.get_units(detail.product_id);
      this.detail.detail_id = detail.detail_id;
      this.detail.purchase_unit_id = detail.purchase_unit_id;
      this.detail.Unit_cost = detail.Unit_cost;
      this.detail.fix_cost = detail.fix_cost;
      this.detail.fix_stock = detail.fix_stock;
      this.detail.stock = detail.stock;
      this.detail.quantity = detail.quantity;
      this.$bvModal.show("form_Update_Detail");
    },

    //------ Submit Update Detail Product

    Update_Detail() {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === this.detail.detail_id) {

           for(var k=0; k<this.units.length; k++){
              if (this.units[k].id == this.detail.purchase_unit_id) {
                if(this.units[k].operator == '/'){
                  this.details[i].stock       = this.detail.fix_stock  * this.units[k].operator_value;
                  this.details[i].unitPurchase    = this.units[k].ShortName;

                }else{
                  this.details[i].stock       = this.detail.fix_stock  / this.units[k].operator_value;
                  this.details[i].unitPurchase    = this.units[k].ShortName;
                }
              }
            }


            if (this.details[i].stock < this.details[i].quantity) {
              this.details[i].quantity = this.details[i].stock;
            } else {
              this.details[i].quantity =1;
            }
            
          
          this.details[i].Unit_cost = this.detail.Unit_cost;
          this.details[i].purchase_unit_id = this.detail.purchase_unit_id;
          this.details[i].Net_cost = this.detail.Unit_cost;

          this.$forceUpdate();
        }
      }
      this.Calcul_Total();
      this.$bvModal.hide("form_Update_Detail");
    },

    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    //---Validate State Fields
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    
    // Search Products
    search(){

      if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
      }

      if (this.search_input.length < 2) {

        return this.product_filter= [];
      }
      if (this.internal_order.from_warehouse != "" &&  this.internal_order.from_warehouse != null) {
        this.timer = setTimeout(() => {
          const product_filter = this.products.filter(product => product.code === this.search_input || product.barcode.includes(this.search_input));
            if(product_filter.length === 1){
                this.SearchProduct(product_filter[0])
            }else{
                this.product_filter=  this.products.filter(product => {
                  return (
                    product.name.toLowerCase().includes(this.search_input.toLowerCase()) ||
                    product.code.toLowerCase().includes(this.search_input.toLowerCase()) ||
                    product.barcode.toLowerCase().includes(this.search_input.toLowerCase())
                    );
                });

                // Check if product_filter is empty and show alert
                if (this.product_filter.length <= 0) {
                  this.makeToast(
                    "warning",
                    "Product Not Found",
                    "Warning"
                  );
                }
            }
        }, 800);
      } else {
        this.makeToast(
          "warning",
          this.$t("SelectWarehouse"),
          this.$t("Warning")
        );
      }

    },

       

    //-------------------- get Result Value Search Product

    getResultValue(result) {
      return result.code + " " + "(" + result.name + ")";
    },

    //--------------------  Submit Search Product

    SearchProduct(result) {
      this.product = {};
      if (
        this.details.length > 0 &&
        this.details.some(detail => detail.code === result.code)
      ) {
        this.makeToast("warning", this.$t("AlreadyAdd"), this.$t("Warning"));
      } else {
        this.product.code = result.code;
        this.product.stock = result.qte_purchase;
        this.product.fix_stock = result.qte;
        if (result.qte_purchase < 1) {
          this.product.quantity = result.qte_purchase;
        } else {
          this.product.quantity = 1;
        }
        this.product.product_variant_id = result.product_variant_id;
        this.Get_Product_Details(result.id, result.product_variant_id);
      }

      this.search_input= '';
      this.$refs.product_autocomplete.value = "";
      this.product_filter = [];
    },

    //-----------------------------------------Calcul Total ------------------------------\\
    Calcul_Total() {
      this.total = 0;
      for (let index = 0; index < this.details.length; index++) {
        this.details[index].subtotal = parseFloat(
          this.details[index].quantity * this.details[index].Net_cost
        );
        this.total = parseFloat(this.total + this.details[index].subtotal);
      }

      this.GrandTotal = parseFloat(this.total);
      this.internal_order.GrandTotal = this.GrandTotal;
    },

    //-----------------------------------Verified QTY ------------------------------\\
    Verified_Qty(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === id) {
          if (isNaN(detail.quantity)) {
            this.details[i].quantity = 1;
          }
          if (detail.quantity > detail.stock) {
            this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
            this.details[i].quantity = detail.stock;
          } else {
            this.details[i].quantity = detail.quantity;
          }
          this.Calcul_Total();
          this.$forceUpdate();
        }
      }
    },

    //-----------------------------------increment QTY ------------------------------\\
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

    //-----------------------------------decrement QTY ------------------------------\\
    decrement(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === id) {
          if (detail.quantity - 1 > 0) {
            if (detail.quantity - 1 > detail.stock) {
              this.makeToast(
                "warning",
                this.$t("LowStock"),
                this.$t("Warning")
              );
            } else {
              this.details[i].quantity = parseFloat(detail.quantity) - 1;
            }
          }
          this.Calcul_Total();
          this.$forceUpdate();
        }
      }
    },

    //-----------------------------------Delete Detail Product ------------------------------\\
    delete_Product_Detail(id) {
      for (var i = 0; i < this.details.length; i++) {
        if (id === this.details[i].detail_id) {
          this.details.splice(i, 1);
          this.Calcul_Total();
        }
      }
    },

    //-----------------------------------Selected From Warehouse ------------------------------\\
    Selected_From_Warehouse(value) {
      this.search_input= '';
      this.product_filter = [];
      this.Get_Products_By_Warehouse(value);
    },

    //-----------------------------------Get Products By Warehouse ------------------------------\\
    Get_Products_By_Warehouse(id) {
      // Start the progress bar.
        NProgress.start();
        NProgress.set(0.1);
      axios
        .get("Products/Warehouse/" + id + "?stock=" + 0)
        .then(response => {
            this.products = response.data;
             NProgress.done();
            })
          .catch(error => {
          });
    },

    //-----------------------------------Get Information Product ------------------------------\\
    Get_Product_Details(product_id, variant_id) {
      axios.get("Products/" + product_id + "/" + variant_id).then(response => {
        this.product.product_id = response.data.id;
        this.product.name = response.data.name;
        this.product.Net_cost = response.data.Net_cost;
        this.product.Unit_cost = response.data.Unit_cost;
        this.product.taxe = 0;
        this.product.tax_method = "1";
        this.product.tax_percent = 0;
        this.product.discount = 0;
        this.product.discount_Method = "2";
        this.product.DiscountNet = 0;
        this.product.unitPurchase = response.data.unitPurchase;
        this.product.purchase_unit_id = response.data.purchase_unit_id;
        this.product.fix_cost = response.data.fix_cost;
        this.product.fix_stock = response.data.fix_stock;
        this.add_product();
        this.Calcul_Total();
      });
    },

    //-----------------------------------Add Product to details ------------------------------\\
    add_product() {
      this.details.push({
        detail_id: this.product.id,
        product_id: this.product.product_id,
        name: this.product.name,
        code: this.product.code,
        quantity: this.product.quantity,
        stock: this.product.stock,
        Net_cost: this.product.Net_cost,
        Unit_cost: this.product.Unit_cost,
        fix_stock: this.product.fix_stock,
        fix_cost: this.product.fix_cost,
        unitPurchase: this.product.unitPurchase,
        purchase_unit_id: this.product.purchase_unit_id,
        product_variant_id: this.product.product_variant_id,
        subtotal: this.product.subtotal,
        taxe: 0,
        tax_percent: 0,
        tax_method: "1",
        discount: 0,
        DiscountNet: 0,
        discount_Method: "2"
      });
    },

    //-----------------------------------Update Internal Order ------------------------------\\
    Update_InternalOrder() {
      this.SubmitProcessing = true;
      NProgress.start();
      NProgress.set(0.1);
      let id = this.$route.params.id;
      axios
        .put(`internal_orders/${id}`, {
          internal_order: this.internal_order,
          details: this.details,
          GrandTotal: this.GrandTotal
        })
        .then(response => {
          NProgress.done();
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          this.SubmitProcessing = false;
          this.$router.push({ name: "index_internal_order" });
        })
        .catch(error => {
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          this.SubmitProcessing = false;
        });
    },

    //-----------------------------------Get Elements ------------------------------\\
    GetElements() {
      let id = this.$route.params.id;
      axios
        .get(`internal_orders/${id}/edit`)
        .then(response => {
          this.internal_order = response.data.internal_order;
          this.details = response.data.details;
          this.warehouses = response.data.warehouses;
          this.to_warehouses = response.data.to_warehouses;
          this.Get_Products_By_Warehouse(this.internal_order.from_warehouse);
          this.Calcul_Total();
          this.isLoading = false;
        })
        .catch(response => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    }
  },

  //-----------------------------Autoload function-------------------
  created() {
    this.GetElements();
  }
};
</script>
