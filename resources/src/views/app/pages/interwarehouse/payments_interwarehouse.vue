<template>
  <div class="main-content">
    <breadcumb :page="$t('PaymentsInterWarehouse')" :folder="$t('InterWarehouseRequests')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else>
      <b-row>
        <b-col lg="12" md="12" sm="12">
          <b-card class="mb-30">
            <b-row>
              <b-col lg="4" md="6" sm="12">
                <h5 class="card-title">{{$t('Request')}}: {{iw_request.Ref}}</h5>
                <p>{{$t('RequesterWarehouse')}}: {{iw_request.requester_warehouse ? iw_request.requester_warehouse.name : ''}}</p>
                <p>{{$t('SupplierWarehouse')}}: {{iw_request.supplier_warehouse ? iw_request.supplier_warehouse.name : ''}}</p>
              </b-col>
              <b-col lg="8" md="6" sm="12" class="text-right">
                <div class="d-flex justify-content-end align-items-center">
                  <div class="mr-4">
                    <h6 class="text-muted">{{$t('Total')}}</h6>
                    <h4>{{currentUser.currency}} {{formatNumber(iw_request.GrandTotal, 2)}}</h4>
                  </div>
                  <div class="mr-4">
                    <h6 class="text-muted">{{$t('Paid')}}</h6>
                    <h4 class="text-success">{{currentUser.currency}} {{formatNumber(iw_request.paid_amount, 2)}}</h4>
                  </div>
                  <div>
                    <h6 class="text-muted">{{$t('Due')}}</h6>
                    <h4 class="text-danger">{{currentUser.currency}} {{formatNumber(iw_request.GrandTotal - iw_request.paid_amount, 2)}}</h4>
                  </div>
                </div>
              </b-col>
            </b-row>
          </b-card>
        </b-col>
      </b-row>

      <b-row>
        <b-col lg="12" md="12" sm="12">
          <b-card :title="$t('PaymentList')">
            <div class="mb-3">
              <b-button 
                v-if="iw_request.paid_amount < iw_request.GrandTotal && currentUserPermissions && currentUserPermissions.includes('interwarehouse_payment_add')"
                variant="primary" 
                @click="New_Payment"
              >
                <i class="i-Add me-2 font-weight-bold"></i> {{$t('AddPayment')}}
              </b-button>
            </div>
            <div class="table-responsive">
              <table class="table table-hover table-bordered table-md">
                <thead>
                  <tr>
                    <th scope="col">{{$t('date')}}</th>
                    <th scope="col">{{$t('Reference')}}</th>
                    <th scope="col">{{$t('Amount')}}</th>
                    <th scope="col">{{$t('PayeBy')}}</th>
                    <th scope="col">{{$t('Action')}}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="payments.length <= 0">
                    <td colspan="5">{{$t('NodataAvailable')}}</td>
                  </tr>
                  <tr v-for="payment in payments" :key="payment.id">
                    <td>{{payment.date}}</td>
                    <td>{{payment.Ref}}</td>
                    <td>{{currentUser.currency}} {{formatNumber(payment.montant, 2)}}</td>
                    <td>{{payment.payment_method}}</td>
                    <td>
                      <div role="group" aria-label="Basic example" class="btn-group">
                        <span
                          v-if="currentUserPermissions && currentUserPermissions.includes('interwarehouse_payment_delete')"
                          title="Delete"
                          class="btn btn-icon btn-danger btn-sm"
                          @click="Remove_Payment(payment.id)"
                        >
                          <i class="i-Close"></i>
                        </span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </b-card>
        </b-col>
      </b-row>
    </div>

    <!-- Modal Add Payment-->
    <validation-observer ref="Add_payment">
      <b-modal
        hide-footer
        size="md"
        id="Add_Payment"
        :title="$t('AddPayment')"
      >
        <b-form @submit.prevent="Submit_Payment">
          <b-row>
            <!-- date -->
            <b-col lg="12" md="12" sm="12">
              <validation-provider
                name="date"
                :rules="{ required: true}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('date')">
                  <b-form-input
                    label="date"
                    :state="getValidationState(validationContext)"
                    aria-describedby="date-feedback"
                    v-model="payment.date"
                    type="date"
                  ></b-form-input>
                  <b-form-invalid-feedback id="date-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

             <!-- Payment choice -->
             <b-col lg="12" md="12" sm="12">
              <validation-provider name="Payment choice" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('Paymentchoice')">
                  <v-select
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    v-model="payment.payment_method"
                    :reduce="label => label.value"
                    :placeholder="$t('PleaseSelect')"
                    :options="
                          [
                          {label: 'Cash', value: 'Cash'},
                          {label: 'Cheque', value: 'Cheque'},
                          {label: 'Credit Card', value: 'Credit Card'},
                          {label: 'Bank Transfer', value: 'Bank Transfer'},
                          ]"
                  ></v-select>
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Amount  -->
            <b-col lg="12" md="12" sm="12">
              <validation-provider
                name="Amount"
                :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Amount')">
                  <b-form-input
                    label="Amount"
                    :placeholder="$t('Amount')"
                    v-model.number="payment.montant"
                    :state="getValidationState(validationContext)"
                    aria-describedby="Amount-feedback"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Amount-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Note -->
            <b-col lg="12" md="12" sm="12">
              <b-form-group :label="$t('Note')">
                <b-form-textarea id="textarea" v-model="payment.notes" rows="3" max-rows="6"></b-form-textarea>
              </b-form-group>
            </b-col>
            <b-col md="12" class="mt-3">
              <b-button
                variant="primary"
                type="submit"
                :disabled="paymentProcessing"
              ><i class="i-Yes me-2 font-weight-bold"></i> {{$t('submit')}}</b-button>
              <div v-once class="typo__p" v-if="paymentProcessing">
                <div class="spinner sm spinner-primary mt-3"></div>
              </div>
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
    title: "Inter-Warehouse Payments"
  },
  data() {
    return {
      isLoading: true,
      paymentProcessing: false,
      iw_request: {},
      payments: [],
      payment: {
        date: new Date().toISOString().slice(0, 10),
        interwarehouse_request_id: "",
        montant: "",
        payment_method: "",
        notes: ""
      }
    };
  },
  computed: {
    ...mapGetters(["currentUser", "currentUserPermissions"])
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

    Get_Payments() {
      let id = this.$route.params.id;
      axios
        .get("interwarehouse_payments/by_request/" + id)
        .then(response => {
          this.iw_request = response.data.request;
          this.payments = response.data.payments;
          this.isLoading = false;
        })
        .catch(error => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    New_Payment() {
      this.payment = {
        date: new Date().toISOString().slice(0, 10),
        interwarehouse_request_id: this.iw_request.id,
        montant: "",
        payment_method: "",
        notes: ""
      };
      this.$bvModal.show("Add_Payment");
    },

    Submit_Payment() {
      this.$refs.Add_payment.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
            if (this.payment.montant > this.iw_request.GrandTotal - this.iw_request.paid_amount) {
                this.makeToast("warning", this.$t("Paying_amount_is_greater_than_Grand_Total"), this.$t("Warning"));
                this.payment.montant = 0;
            } else {
                this.Create_Payment();
            }
        }
      });
    },

    Create_Payment() {
      this.paymentProcessing = true;
      axios
        .post("interwarehouse_payments", this.payment)
        .then(response => {
          this.paymentProcessing = false;
          this.$bvModal.hide("Add_Payment");
          this.makeToast(
            "success",
            this.$t("Create.TitlePayment"),
            this.$t("Success")
          );
          this.Get_Payments();
        })
        .catch(error => {
          this.paymentProcessing = false;
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },

    Remove_Payment(id) {
      this.$swal({
        title: this.$t("Delete_Title"),
        text: this.$t("Delete_Text"),
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Delete_cancelButtonText"),
        confirmButtonText: this.$t("Delete_confirmButtonText")
      }).then(result => {
        if (result.value) {
          axios
            .delete("interwarehouse_payments/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              this.Get_Payments();
            })
            .catch(() => {
              this.$swal(
                this.$t("Delete_Failed"),
                this.$t("Delete_Therewassomethingwronge"),
                "warning"
              );
            });
        }
      });
    },

    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    }
  },

  created() {
    this.Get_Payments();
  }
};
</script>
