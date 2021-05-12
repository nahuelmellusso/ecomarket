import axios from "axios";
import createPersistedState from "vuex-persistedstate";
import Cookies from "js-cookie";

export const state = {
  cart: [],
  total: 0,
  subtotal:0,
  cartCount: 0,
  item: {
    variant: {},
    product: {}
  }
};
// cart: Cookies.get('cart') ? JSON.parse(Cookies.get('cart')) : [] ,
// total: Cookies.get('cart') ?  JSON.parse(Cookies.get('cart')).reduce((a, b) => a + (b['total'] || 0), 0) : 0,
// cartCount: Cookies.get('cart') ? JSON.parse(Cookies.get('cart')).length : 0 ,

export const getters = {
  cart: state => state.cart,
  total: state => parseFloat(state.total).toFixed(2),
  count: state => state.cartCount
};
export const mutations = {
  addItem(state, item) {

    let index = state.cart.findIndex(it => it.id == item.product.id);

    // if product exist , update qty
    if (index > -1) {
      state.cart[index].qty = item.qty;
    } else {
      // push new product to cart

      state.cart.push(item);
      state.cartCount++;
    }
   
    state.total = state.cart.reduce((prev, cur) =>  {
      return prev + cur.total;
    }, 0);
  
  },
  removeItem(state, id) {
    const i = state.cart.findIndex(it => it.id == id);
    let auxcart = state.cart.filter( it => it.id !== id)
    console.log(auxcart)
    state.cart = []
    auxcart.map(c =>  {
      let item = Object.assign({},c)
      item.product = Object.assign({},c.product)
      state.cart.push(item)
    })

      state.cartCount--;
      
      state.total = state.cart.reduce((prev, cur) =>  {
        return prev + cur.total;
      }, 0);
    // }
  },
  cleanCart(state) {
    state.cart = [];
    state.cartCount = 0;
    state.total = 0;
  }
};
export const actions = {
  async fetchProducts({ commit, state }, opt) {
    try {
      commit("setPage", opt.pag);
      const { data } = await axios.get(`/api/products?page=${opt.pag}`);
      commit("setProducts", data.data);
      commit("setPagination", data);
    } catch (e) {
      commit("setProducts", { data: [] });
    }
  },
  getCartTotal(state) {
    var total = 0;
    total = state.cart.map(function(v) {
      if (v.product.sale_type === "Unidad") {
        total = total + v.product.price * v.qty;
      } else {
        total = total + v.variant.price * v.qty;
      }
    });
  }
};
