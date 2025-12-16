<template>
  <div class="main-content">
    <breadcumb :page="$t('InterWarehouseRequests')" :folder="$t('InterWarehouse')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else>
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="requests"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
          enabled: true,
          placeholder: $t('Search_this_table'),  
        }"
        :select-options="{ 
          enabled: true ,
          clearSelectionText: '',
        }"
        @on-selected-rows-change="selectionChanged"
        :pagination-options="{
          enabled: true,
          mode: 'records',
          nextLabel: 'next',
          prevLabel: 'prev',
        }"
        styleClass="tableOne table-hover vgt-table"
      >
        <div slot="selected-row-actions">
          <button class="btn btn-danger btn-sm" @click="delete_by_selected()">{{$t('Del')}}</button>
        </div>
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button variant="outline-info ripple m-1" size="sm" v-b-toggle.sidebar-right>
            <i class="i-Filter-2"></i>
            {{ $t("Filter") }}
          </b-button>
          <b-button @click="Generate_PDF()" size="sm" variant="outline-success ripple m-1">
            <i class="i-File-Copy"></i> PDF
          </b-button>
           <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger ripple m-1"
              :data="requests"
              :columns="columns"
              :file-name="'interwarehouse_requests'"
              :file-type="'xlsx'"
              :sheet-name="'requests'"
              >
              <i class="i-File-Excel"></i> EXCEL
          </vue-excel-xlsx>
          <router-link
            class="btn-sm btn btn-primary ripple btn-icon m-1"
            v-if="currentUserPermissions && currentUserPermissions.includes('interwarehouse_add')"
            to="/app/interwarehouse/create"
          >
            <span class="ul-btn__icon">
              <i class="i-Add"></i>
            </span>
            <span class="ul-btn__text ml-1">{{$t('Add')}}</span>
          </router-link>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'actions'">
            
            <!-- View/PDF -->
            <a title="PDF" v-b-tooltip.hover @click="download_pdf(props.row , props.row.id)">
              <i class="i-File-TXT text-25 text-primary cursor-pointer"></i>
            </a>

            <!-- Show Details -->
            <a title="View" v-b-tooltip.hover @click="showDetails(props.row.id)">
              <i class="i-Eye text-25 text-info cursor-pointer"></i>
            </a>

            <!-- Edit (only if draft) -->
            <router-link
              v-if="currentUserPermissions && currentUserPermissions.includes('interwarehouse_edit') && props.row.statut == 'draft'"
              title="Edit"
              v-b-tooltip.hover
              :to="{ name:'edit_interwarehouse', params: { id: props.row.id } }"
            >
              <i class="i-Edit text-25 text-success"></i>
            </router-link>

            <!-- Send (only if draft) -->
            <a
              title="Send"
              v-b-tooltip.hover
              v-if="currentUserPermissions && currentUserPermissions.includes('interwarehouse_edit') && props.row.statut == 'draft'"
              @click="Send_Request(props.row.id)"
            >
              <i class="i-Paper-Plane text-25 text-primary cursor-pointer"></i>
            </a>

            <!-- Create Proforma (Supplier - only if sent) -->
            <router-link
              v-if="currentUserPermissions && currentUserPermissions.includes('interwarehouse_proforma') && props.row.statut == 'sent'"
              title="Create Proforma"
              v-b-tooltip.hover
              :to="{ name:'create_proforma', params: { id: props.row.id } }"
            >
              <i class="i-File-Edit text-25 text-warning"></i>
            </router-link>

            <!-- Validate Proforma (Requester - only if proforma_sent) -->
            <a
              title="Validate Proforma"
              v-b-tooltip.hover
              v-if="currentUserPermissions && currentUserPermissions.includes('interwarehouse_edit') && props.row.statut == 'proforma_sent'"
              @click="Validate_Proforma(props.row.id)"
            >
              <i class="i-Yes text-25 text-success cursor-pointer"></i>
            </a>

            <!-- Payments (if validated/in_payment) -->
            <router-link
              v-if="currentUserPermissions && currentUserPermissions.includes('interwarehouse_payment') && ['validated', 'in_payment', 'paid', 'delivered'].includes(props.row.statut)"
              title="Payments"
              v-b-tooltip.hover
              :to="{ name:'payments_interwarehouse', params: { id: props.row.id } }"
            >
              <i class="i-Money-Bag text-25 text-success"></i>
            </router-link>

            <!-- Delete (only if draft or rejected) -->
            <a
              title="Delete"
              v-b-tooltip.hover
              v-if="currentUserPermissions && currentUserPermissions.includes('interwarehouse_delete') && ['draft', 'rejected'].includes(props.row.statut)"
              @click="Remove_Request(props.row.id)"
            >
              <i class="i-Close-Window text-25 text-danger"></i>
            </a>
          </span>

          <div v-else-if="props.column.field == 'statut'">
            <span v-if="props.row.statut == 'draft'" class="badge badge-outline-secondary">{{$t('Draft')}}</span>
            <span v-else-if="props.row.statut == 'sent'" class="badge badge-outline-primary">{{$t('Sent')}}</span>
            <span v-else-if="props.row.statut == 'proforma_sent'" class="badge badge-outline-warning">{{$t('ProformaSent')}}</span>
            <span v-else-if="props.row.statut == 'validated'" class="badge badge-outline-info">{{$t('Validated')}}</span>
            <span v-else-if="props.row.statut == 'in_payment'" class="badge badge-outline-info">{{$t('InPayment')}}</span>
            <span v-else-if="props.row.statut == 'paid'" class="badge badge-outline-success">{{$t('Paid')}}</span>
            <span v-else-if="props.row.statut == 'delivered'" class="badge badge-outline-success">{{$t('Delivered')}}</span>
            <span v-else-if="props.row.statut == 'rejected'" class="badge badge-outline-danger">{{$t('Rejected')}}</span>
            <span v-else-if="props.row.statut == 'closed'" class="badge badge-outline-secondary">{{$t('Closed')}}</span>
          </div>

          <div v-else-if="props.column.field == 'payment_status'">
             <span class="badge badge-outline-info">{{props.row.payment_percentage}}%</span>
          </div>
        </template>
      </vue-good-table>
    </div>

    <!-- multiple filters -->
    <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
      <div class="px-3 py-2">
        <b-row>
          <!-- Reference  -->
          <b-col md="12">
            <b-form-group :label="$t('Reference')">
              <b-form-input label="Reference" :placeholder="$t('Reference')" v-model="Filter_Ref"></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Requester warehouse  -->
          <b-col md="12">
            <b-form-group :label="$t('RequesterWarehouse')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Warehouse')"
                v-model="Filter_Requester"
                :options="warehouses.map(w => ({label: w.name, value: w.id}))"
              />
            </b-form-group>
          </b-col>

          <!-- Supplier warehouse  -->
          <b-col md="12">
            <b-form-group :label="$t('SupplierWarehouse')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Warehouse')"
                v-model="Filter_Supplier"
                :options="warehouses.map(w => ({label: w.name, value: w.id}))"
              />
            </b-form-group>
          </b-col>

          <!-- Status  -->
          <b-col md="12">
            <b-form-group :label="$t('Status')">
              <v-select
                v-model="Filter_status"
                :reduce="label => label.value"
                :placeholder="$t('Choose_Status')"
                :options="[
                  {label: 'Draft', value: 'draft'},
                  {label: 'Sent', value: 'sent'},
                  {label: 'Proforma Sent', value: 'proforma_sent'},
                  {label: 'Validated', value: 'validated'},
                  {label: 'In Payment', value: 'in_payment'},
                  {label: 'Paid', value: 'paid'},
                  {label: 'Delivered', value: 'delivered'},
                  {label: 'Rejected', value: 'rejected'},
                  {label: 'Closed', value: 'closed'},
                ]"
              ></v-select>
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12">
            <b-button
              @click="Get_Requests(serverParams.page)"
              variant="primary ripple m-1"
              size="sm"
              block
            >
              <i class="i-Filter-2"></i>
              {{ $t("Filter") }}
            </b-button>
          </b-col>
          <b-col md="6" sm="12">
            <b-button @click="Reset_Filter()" variant="danger ripple m-1" size="sm" block>
              <i class="i-Power-2"></i>
              {{ $t("Reset") }}
            </b-button>
          </b-col>
        </b-row>
      </div>
    </b-sidebar>

    <!-- Details Modal -->
    <b-modal ok-only size="lg" id="showDetails" :title="$t('InterWarehouseRequestDetails')">
      <b-row>
        <b-col lg="5" md="12" sm="12" class="mt-3">
          <table class="table table-hover table-bordered table-sm">
            <tbody>
              <tr>
                <td>{{$t('date')}}</td>
                <th>{{request_details.date}}</th>
              </tr>
              <tr>
                <td>{{$t('Reference')}}</td>
                <th>{{request_details.Ref}}</th>
              </tr>
              <tr>
                <td>{{$t('RequesterWarehouse')}}</td>
                <th>{{request_details.requester_warehouse}}</th>
              </tr>
              <tr>
                <td>{{$t('SupplierWarehouse')}}</td>
                <th>{{request_details.supplier_warehouse}}</th>
              </tr>
              <tr>
                <td>{{$t('Total')}}</td>
                <th>{{currentUser.currency}}{{formatNumber(request_details.GrandTotal ,2)}}</th>
              </tr>
              <tr>
                <td>{{$t('Paid')}}</td>
                <th>{{currentUser.currency}}{{formatNumber(request_details.paid_amount ,2)}}</th>
              </tr>
              <tr>
                <td>{{$t('Status')}}</td>
                <th>{{request_details.statut}}</th>
              </tr>
            </tbody>
          </table>
        </b-col>
        <b-col lg="7" md="12" sm="12" class="mt-3">
          <div class="table-responsive">
            <table class="table table-hover table-bordered table-sm">
              <thead>
                <tr>
                  <th scope="col">{{$t('ProductName')}}</th>
                  <th scope="col">{{$t('CodeProduct')}}</th>
                  <th scope="col">{{$t('Quantity')}}</th>
                  <th scope="col">{{$t('SubTotal')}}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="detail in details">
                  <td>{{detail.product_name}}</td>
                  <td>{{detail.product_code}}</td>
                  <td>{{formatNumber(detail.quantity ,2)}} {{detail.unit_name}}</td>
                  <td>{{currentUser.currency}} {{detail.total.toFixed(2)}}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </b-col>
      </b-row>
    </b-modal>

  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

export default {
  metaInfo: {
    title: "Inter-Warehouse Requests"
  },
  data() {
    return {
      isLoading: true,
      serverParams: {
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      selectedIds: [],
      search: "",
      totalRows: "",
      limit: "10",
      Filter_status: "",
      Filter_Ref: "",
      Filter_Requester: "",
      Filter_Supplier: "",
      requests: [],
      warehouses: [],
      request_details: {},
      details: [],
    };
  },
  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"]),
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
          label: this.$t("RequesterWarehouse"),
          field: "requester_warehouse",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("SupplierWarehouse"),
          field: "supplier_warehouse",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Total"),
          field: "GrandTotal",
          type: "decimal",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Paid"),
          field: "paid_amount",
          type: "decimal",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("PaymentStatus"),
          field: "payment_status",
          tdClass: "text-center",
          thClass: "text-center"
        },
        {
          label: this.$t("Status"),
          field: "statut",
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
    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Requests(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Requests(1);
      }
    },

    //---- Event Select Rows
    selectionChanged({ selectedRows }) {
      this.selectedIds = [];
      selectedRows.forEach((row, index) => {
        this.selectedIds.push(row.id);
      });
    },

    //---- Event sort change
    onSortChange(params) {
      this.updateParams({
        sort: {
          type: params[0].type,
          field: params[0].field
        }
      });
      this.Get_Requests(this.serverParams.page);
    },

    //---- Event on Search
    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Requests(this.serverParams.page);
    },

    setToStrings() {
      if (this.Filter_Requester === null) this.Filter_Requester = "";
      if (this.Filter_Supplier === null) this.Filter_Supplier = "";
      if (this.Filter_status === null) this.Filter_status = "";
    },

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

    Reset_Filter() {
      this.search = "";
      this.Filter_status = "";
      this.Filter_Ref = "";
      this.Filter_Requester = "";
      this.Filter_Supplier = "";
      this.Get_Requests(this.serverParams.page);
    },

    Get_Requests(page) {
      NProgress.start();
      NProgress.set(0.1);
      this.setToStrings();
      axios
        .get(
          "interwarehouse_requests?page=" +
            page +
            "&Ref=" +
            this.Filter_Ref +
            "&statut=" +
            this.Filter_status +
            "&requester_warehouse_id=" +
            this.Filter_Requester +
            "&supplier_warehouse_id=" +
            this.Filter_Supplier +
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
          this.requests = response.data.interwarehouse_requests;
          this.totalRows = response.data.totalRows;
          
          // Extract warehouses for filter
          const all_warehouses = [];
          this.requests.forEach(req => {
             if(req.requester_warehouse_id) all_warehouses.push({id: req.requester_warehouse_id, name: req.requester_warehouse});
             if(req.supplier_warehouse_id) all_warehouses.push({id: req.supplier_warehouse_id, name: req.supplier_warehouse});
          });
          // Unique warehouses
          this.warehouses = [...new Map(all_warehouses.map(item => [item['id'], item])).values()];

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

    showDetails(id) {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get("interwarehouse_requests/" + id)
        .then(response => {
          this.request_details = response.data.interwarehouse_request;
          this.details = response.data.details;
          this.$bvModal.show("showDetails");
          NProgress.done();
        })
        .catch(response => {
          NProgress.done();
        });
    },

    download_pdf(request, id) {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get("interwarehouse_request_pdf/" + id, {
          responseType: "blob",
          headers: {
            "Content-Type": "application/json"
          }
        })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute("download", "InterWarehouse-" + request.Ref + ".pdf");
          document.body.appendChild(link);
          link.click();
          setTimeout(() => NProgress.done(), 500);
        })
        .catch(() => {
          setTimeout(() => NProgress.done(), 500);
        });
    },

    Remove_Request(id) {
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
          NProgress.start();
          NProgress.set(0.1);
          axios
            .delete("interwarehouse_requests/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              this.Get_Requests(this.serverParams.page);
            })
            .catch(() => {
              setTimeout(() => NProgress.done(), 500);
              this.$swal(
                this.$t("Delete_Failed"),
                this.$t("Delete_Therewassomethingwronge"),
                "warning"
              );
            });
        }
      });
    },

    delete_by_selected() {
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
          NProgress.start();
          NProgress.set(0.1);
          axios
            .post("interwarehouse_requests/delete/by_selection", {
              selectedIds: this.selectedIds
            })
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              this.Get_Requests(this.serverParams.page);
            })
            .catch(() => {
              setTimeout(() => NProgress.done(), 500);
              this.$swal(
                this.$t("Delete_Failed"),
                this.$t("Delete_Therewassomethingwronge"),
                "warning"
              );
            });
        }
      });
    },

    Send_Request(id) {
       this.$swal({
        title: "Envoyer la demande?",
        text: "La demande sera envoyée à l'entrepôt fournisseur.",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: "Annuler",
        confirmButtonText: "Envoyer"
      }).then(result => {
        if (result.value) {
          NProgress.start();
          axios.post("interwarehouse_requests/" + id + "/send")
            .then(() => {
              this.$swal("Envoyé!", "La demande a été envoyée.", "success");
              this.Get_Requests(this.serverParams.page);
              NProgress.done();
            })
            .catch(() => {
              NProgress.done();
              this.$swal("Erreur", "Une erreur est survenue.", "error");
            });
        }
      });
    },

    Validate_Proforma(id) {
       this.$swal({
        title: "Valider le proforma?",
        text: "Vous acceptez les conditions du proforma.",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: "Annuler",
        confirmButtonText: "Valider"
      }).then(result => {
        if (result.value) {
          NProgress.start();
          axios.post("interwarehouse_requests/" + id + "/accept")
            .then(() => {
              this.$swal("Validé!", "Le proforma a été validé.", "success");
              this.Get_Requests(this.serverParams.page);
              NProgress.done();
            })
            .catch(() => {
              NProgress.done();
              this.$swal("Erreur", "Une erreur est survenue.", "error");
            });
        }
      });
    },
  },

  created() {
    this.Get_Requests(1);
  }
};
</script>
