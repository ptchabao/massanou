<template>
  <div class="main-content">
    <breadcumb :page="$t('DeliveriesInterWarehouse')" :folder="$t('InterWarehouseRequests')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else>
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="deliveries"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
        placeholder: $t('Search_this_table'),
        enabled: true,
      }"
        :pagination-options="{
        enabled: true,
        mode: 'records',
        nextLabel: 'next',
        prevLabel: 'prev',
      }"
        styleClass="tableOne table-hover vgt-table"
      >
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'actions'">
            <a
              @click="Delivery_PDF(props.row)"
              class="cursor-pointer text-info mr-2"
              title="PDF"
            >
              <i class="i-File-Copy text-25"></i>
            </a>
            <a
              @click="Edit_Delivery(props.row)"
              v-if="currentUserPermissions && currentUserPermissions.includes('interwarehouse_delivery_edit')"
              title="Edit"
              class="cursor-pointer text-success mr-2"
            >
              <i class="i-Edit text-25"></i>
            </a>
          </span>

          <div v-else-if="props.column.field == 'status'">
            <span
              v-if="props.row.status == 'ordered'"
              class="badge badge-outline-warning"
            >{{$t('Ordered')}}</span>
            <span
              v-else-if="props.row.status == 'packed'"
              class="badge badge-outline-info"
            >{{$t('Packed')}}</span>
            <span
              v-else-if="props.row.status == 'shipped'"
              class="badge badge-outline-secondary"
            >{{$t('Shipped')}}</span>
             <span
              v-else-if="props.row.status == 'delivered'"
              class="badge badge-outline-success"
            >{{$t('Delivered')}}</span>
            <span v-else class="badge badge-outline-danger">{{$t('Cancelled')}}</span>
          </div>
        </template>
      </vue-good-table>
    </div>

    <!-- Modal Edit Delivery -->
    <validation-observer ref="delivery_ref">
      <b-modal hide-footer size="md" id="modal_delivery" :title="$t('Edit')">
        <b-form @submit.prevent="Submit_Delivery">
          <b-row>
            <!-- Status  -->
            <b-col md="12">
              <validation-provider name="Status" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('Status') + ' ' + '*'">
                  <v-select
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    v-model="delivery.status"
                    :reduce="label => label.value"
                    :placeholder="$t('Choose_Status')"
                    :options="
                                [
                                  {label: 'Ordered', value: 'ordered'},
                                  {label: 'Packed', value: 'packed'},
                                  {label: 'Shipped', value: 'shipped'},
                                  {label: 'Delivered', value: 'delivered'},
                                  {label: 'Cancelled', value: 'cancelled'},
                                ]"
                  ></v-select>
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <b-col md="12">
              <b-form-group :label="$t('delivered_to')">
                <b-form-input
                  label="delivered_to"
                  v-model="delivery.delivered_to"
                  :placeholder="$t('delivered_to')"
                ></b-form-input>
              </b-form-group>
            </b-col>

            <b-col md="12">
              <b-form-group :label="$t('Adress')">
                <textarea
                  v-model="delivery.shipping_address"
                  rows="4"
                  class="form-control"
                  :placeholder="$t('Enter_Address')"
                ></textarea>
              </b-form-group>
            </b-col>

            <b-col md="12">
              <b-form-group :label="$t('Please_provide_any_details')">
                <textarea
                  v-model="delivery.shipping_details"
                  rows="4"
                  class="form-control"
                  :placeholder="$t('Please_provide_any_details')"
                ></textarea>
              </b-form-group>
            </b-col>

            <b-col md="12" class="mt-3">
              <b-button
                variant="primary"
                type="submit"
                :disabled="SubmitProcessing"
              ><i class="i-Yes me-2 font-weight-bold"></i> {{$t('submit')}}</b-button>
              <div v-once class="typo__p" v-if="SubmitProcessing">
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
    title: "Inter-Warehouse Deliveries"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing: false,
      serverParams: {
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      totalRows: "",
      search: "",
      limit: "10",
      deliveries: [],
      delivery: {}
    };
  },

  computed: {
    ...mapGetters(["currentUserPermissions"]),
    columns() {
      return [
        {
          label: this.$t("date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Reference"),
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Request"),
          field: "request_ref",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("RequesterWarehouse"),
          field: "requester_warehouse_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("SupplierWarehouse"),
          field: "supplier_warehouse_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Status"),
          field: "status",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Action"),
          field: "actions",
          tdClass: "text-right",
          thClass: "text-right",
          sortable: false
        }
      ];
    }
  },

  methods: {
    Submit_Delivery() {
      this.$refs.delivery_ref.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Update_Delivery();
        }
      });
    },

    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Deliveries(currentPage);
      }
    },

    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Deliveries(1);
      }
    },

    onSortChange(params) {
      this.updateParams({
        sort: {
          type: params[0].type,
          field: params[0].field
        }
      });
      this.Get_Deliveries(this.serverParams.page);
    },

    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Deliveries(this.serverParams.page);
    },

    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    Get_Deliveries(page) {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get(
          "interwarehouse_deliveries?page=" +
            page +
            "&SortField=" +
            this.serverParams.sort.field +
            "&SortType=" +
            this.serverParams.sort.type +
            "&search=" +
            this.search +
            "&limit=" +
            this.limit
        )
        .then(response => {
          this.deliveries = response.data.deliveries;
          this.totalRows = response.data.totalRows;
          NProgress.done();
          this.isLoading = false;
        })
        .catch(response => {
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    Edit_Delivery(delivery) {
      this.delivery = delivery;
      this.$bvModal.show("modal_delivery");
    },

    Update_Delivery() {
      this.SubmitProcessing = true;
      axios
        .put("interwarehouse_deliveries/" + this.delivery.id, {
          shipping_address: this.delivery.shipping_address,
          delivered_to: this.delivery.delivered_to,
          shipping_details: this.delivery.shipping_details,
          status: this.delivery.status
        })
        .then(response => {
          this.makeToast(
            "success",
            this.$t("Updated_in_successfully"),
            this.$t("Success")
          );
          this.SubmitProcessing = false;
          this.$bvModal.hide("modal_delivery");
          this.Get_Deliveries(this.serverParams.page);
        })
        .catch(error => {
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          this.SubmitProcessing = false;
        });
    },

    Delivery_PDF(delivery) {
        // Assuming there is a route for PDF generation
        // window.open('/api/interwarehouse_deliveries/pdf/' + delivery.id, '_blank');
        // Actually, usually we use axios to get blob or just open URL.
        // Let's check how other modules do it.
        // In `shipments.vue`, it generates PDF client-side using jsPDF.
        // But `InterwarehouseDeliveryController` has `generatePdf`.
        // So I should use that.
        axios
        .get("interwarehouse_deliveries/pdf/" + delivery.id, {
            responseType: "blob" 
        })
        .then(response => {
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement("a");
            link.href = url;
            link.setAttribute("download", "Delivery_" + delivery.Ref + ".pdf");
            document.body.appendChild(link);
            link.click();
        })
        .catch(error => {
             this.makeToast("danger", "Error generating PDF", "Error");
        });
    }
  },

  created() {
    this.Get_Deliveries(1);
  }
};
</script>
