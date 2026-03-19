import { defineStore } from 'pinia'
import { encrypt, decrypt } from '../utils/crypto';

export const useLoadingStore = defineStore('loading', {

  state: () => ({
    has_error: false,
    fullPage: true,
    isLoading: false,
    isGifLoader: false,
    loader: "spinner",
    text: "Loading Data..",
    loaderColor: "rgb(95,116,253)",
  }),
  getters: {
  },
  actions: {
  }
})
