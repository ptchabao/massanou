<template>
  <div class="main-content">
    <breadcumb :page="$t('CreateInterWarehouseRequest')" :folder="$t('InterWarehouseRequests')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <validation-observer ref="Create_InterWarehouse" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Request">
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
                        v-model="iw_request.date"
                      ></b-form-input>
                      <b-form-invalid-feedback
                        id="OrderTax-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>
                
                <!-- Requester Warehouse (From) -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider name="Requester Warehouse" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('RequesterWarehouse') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        :disabled="details.length > 0"
                        v-model="iw_request.requester_warehouse_id"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Warehouse')"
                        :options="warehouses.map(w => ({label: w.name, value: w.id}))"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Supplier Warehouse (To) -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider name="Supplier Warehouse" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('SupplierWarehouse') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        @input="Selected_Supplier_Warehouse"
                        v-model="iw_request.supplier_warehouse_id"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Warehouse')"
                        :options="all_warehouses.filter(w => w.id !== iw_request.requester_warehouse_id).map(w => ({label: w.name, value: w.id}))"
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
                          <th scope="col">{{$t('Qty')}}</th>
                          <th scope="col" class="text-center">
                            <i class="fa fa-trash"></i>
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-if="details.length <=0">
                          <td colspan="4">{{$t('NodataAvailable')}}</td>
                        </tr>
                        <tr v-for="detail in details">
                          <td>{{detail.detail_id}}</td>
                          <td>
                            <span>{{detail.code}}</span>
                            <br>
                            <span class="badge badge-success">{{detail.name}}</span>
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
                    <b-button variant="primary" @click="Submit_Request" :disabled="SubmitProcessing"><i class="i-Yes me-2 font-weight-bold"></i> {{$t('submit')}}</b-button>
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

  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Create Inter-Warehouse Request"
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
      warehouses: [],
      all_warehouses: [],
      products: [],
      iw_request: {
        requester_warehouse_id: "",
        supplier_warehouse_id: "",
        statut: "draft",
        notes: "",
        date: new Date().toISOString().slice(0, 10),
      },
      product: {
        id: "",
        code: "",
        quantity: 1,
        name: "",
        unitPurchase: "",
        purchase_unit_id:"",
        product_id: "",
        detail_id: "",
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

    Submit_Request() {
      this.$refs.Create_InterWarehouse.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Create_Request();
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
    
    search(){
      if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
      }

      if (this.search_input.length < 2) {
        return this.product_filter= [];
      }
      if (this.iw_request.supplier_warehouse_id != "" &&  this.iw_request.supplier_warehouse_id != null) {
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

    getResultValue(result) {
      return result.code + " " + "(" + result.name + ")";
    },

    SearchProduct(result) {
      this.product = {};
      if (
        this.details.length > 0 &&
        this.details.some(detail => detail.code === result.code)
      ) {
        this.makeToast("warning", this.$t("AlreadyAdd"), this.$t("Warning"));
      } else {
        this.product.code = result.code;
        this.product.quantity = 1;
        this.product.product_variant_id = result.product_variant_id;
        this.Get_Product_Details(result.id, result.product_variant_id);
      }

      this.search_input= '';
      this.$refs.product_autocomplete.value = "";
      this.product_filter = [];
    },

    Verified_Qty(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === id) {
          if (isNaN(detail.quantity)) {
            this.details[i].quantity = 1;
          }
          this.$forceUpdate();
        }
      }
    },

    increment(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === id) {
          this.details[i].quantity = parseFloat(detail.quantity) + 1;
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
          this.$forceUpdate();
        }
      }
    },

    delete_Product_Detail(id) {
      for (var i = 0; i < this.details.length; i++) {
        if (id === this.details[i].detail_id) {
          this.details.splice(i, 1);
        }
      }
    },

    Selected_Supplier_Warehouse(value) {
      this.search_input= '';
      this.product_filter = [];
      this.Get_Products_By_Warehouse(value);
    },

    Get_Products_By_Warehouse(id) {
        NProgress.start();
        NProgress.set(0.1);
      axios
        .get("get_Products_by_warehouse/" + id + "?stock=" + 0)
        .then(response => {
            this.products = response.data;
             NProgress.done();
            })
          .catch(error => {
          });
    },

    Get_Product_Details(product_id, variant_id) {
      axios.get("show_product_data/" + product_id + "/" + variant_id).then(response => {
        this.product.product_id = response.data.id;
        this.product.name = response.data.name;
        this.product.unitPurchase = response.data.unitPurchase;
        this.product.purchase_unit_id = response.data.purchase_unit_id;
        this.add_product();
      });
    },

    add_product() {
      this.details.push({
        detail_id: this.product.id,
        product_id: this.product.product_id,
        name: this.product.name,
        code: this.product.code,
        quantity: this.product.quantity,
        unitPurchase: this.product.unitPurchase,
        purchase_unit_id: this.product.purchase_unit_id,
        product_variant_id: this.product.product_variant_id,
      });
    },

    Create_Request() {
      this.SubmitProcessing = true;
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("interwarehouse_requests", {
          request: this.iw_request,
          details: this.details,
        })
        .then(response => {
          NProgress.done();
          this.makeToast(
            "success",
            "Demande créée avec succès",
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
      axios
        .get("interwarehouse_requests/create")
        .then(response => {
          this.warehouses = response.data.warehouses;
          this.all_warehouses = response.data.all_warehouses;
          this.isLoading = false;
        })
        .catch(error => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    }
  },

  created() {
    this.GetElements();
  }
};
</script>
