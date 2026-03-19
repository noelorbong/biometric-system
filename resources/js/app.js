/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

import './bootstrap';
import '../css/app.css';
import 'swiper/css'
import 'swiper/css/pagination'
import 'vue-loading-overlay/dist/css/index.css';
import "vue-awesome-paginate/dist/style.css";
import 'vue-select/dist/vue-select.css';

import App from './App.vue'
import Router from '@/router'
import axios from 'axios'
import VueApexCharts from 'vue3-apexcharts'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import VueTailwindDatepicker from "vue-tailwind-datepicker";
import VueAwesomePaginate from "vue-awesome-paginate";
import JsonExcel from "vue-json-excel3";
import Loading from 'vue-loading-overlay';
import vSelect from 'vue-select'
import { useAuthStore } from '@/store/AuthStore'
import { registerGlobalHelpers } from './globals/helpers'
import { API_BASE_URL } from './config/api'
import { markRaw, createApp } from 'vue'
import { createPinia } from 'pinia'


/**
 * Next, we will create a fresh Vue application instance. You may then begin
 * registering components with the application instance so they are ready
 * to use in your application's views. An example is included for you.
 */

const app = createApp(App);
registerGlobalHelpers(app)
const pinia = createPinia();
pinia.use(({ store }) => {store.$router = markRaw(Router)})
pinia.use(piniaPluginPersistedstate)

let isHandlingUnauthorized = false

axios.defaults.baseURL = API_BASE_URL
axios.defaults.withCredentials = true

axios.interceptors.response.use(
	(response) => response,
	(error) => {
		const status = error.response?.status
		const message = error.response?.data?.message
		const isUnauthorized = status === 401 || message === 'Unauthenticated.'

		if (isUnauthorized && !isHandlingUnauthorized) {
			isHandlingUnauthorized = true

			const authStore = useAuthStore(pinia)
			authStore.clearAccount()

			setTimeout(() => {
				isHandlingUnauthorized = false
			}, 0)
		}

		return Promise.reject(error)
	}
)


app.component("downloadExcel", JsonExcel);
app.component('loading', Loading);
app.component('v-select', vSelect)

app.use(VueTailwindDatepicker);
app.use(pinia)
app.use(Router)
app.use(VueAwesomePaginate)
app.use(VueApexCharts)
app.mount('#app');
