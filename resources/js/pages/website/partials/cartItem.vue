<template>
         <tr id="cart-item">
            <td class="shoping__cart__item">
                <img :src="getImage" alt="">
                <h5>{{ item.product.title }}</h5>
            </td>
            <td class="shoping__cart__price">
                {{getPrice}}
            </td>
            <td class="shoping__cart__quantity">
                <div class="quantity">
                    <div class="pro-qty">
                        <i class="fas fa-chevron-left" @click.prevent="qtyMinus" ></i>
                        <input type="tel" v-model="item.qty">
                        <i class="fas fa-chevron-right" @click.prevent="qtyPlus"></i>
                    </div>
                </div>
            </td>
            <td class="shoping__cart__price ">
                    %{{item.product.discount}}
            </td>
            <td class="shoping__cart__total">
                ${{ getTotal }}
            </td>
            <td class="shoping__cart__item__close">
                <i class="far fa-times-circle text-danger" @click="deleteConfirmation"></i>
            </td>
        </tr>
    
</template>
<script>
    import { mapGetters } from 'vuex'
    import Swal from 'sweetalert2'
    export default {
        props:{
            item:{
                type:Object,
                required:true,
            }
        },
        data(){
             return {
                currentItem:{},
                total:0,
                product:{}
            }
        }
           
        ,
        mounted(){
            this.currentItem = Object.assign({},this.item)
            this.total = this.getItemTotal(this.currentItem)
        },
        computed: {
            ...mapGetters({
                 
            }),
             getPrice(){

                 if(this.item.product.sale_type === 'Unidad' ){

                    return this.item.product.price

                 }else{

                    const index = this.item.product.variants.findIndex(p => p.id == this.item.variant)
                    return this.item.product.variants[index].price

                 }
             },
             getTotal(){
                 return this.total
             },
             getImage(){
                return this.item.product.images.length ?  '/productos/'+ this.item.product.images[0].name : '/img/no-image.png'
            }
                
        },
        methods: {
            qtyPlus(){
               this.currentItem.qty++
               this.updateCart()
           },
           qtyMinus(){
               if(this.currentItem.qty > 1 )
                this.currentItem.qty--
                this.updateCart()
           },
           async updateCart(){
                this.total = this.getItemTotal(this.currentItem)
                this.currentItem.total = this.total
                await this.$store.commit('cart/addItem', this.currentItem)
                this.confirmMsg()
           },

            confirmMsg(){
                Swal.fire({
                    toast:true,
                    position: 'top-end',
                    text:'Producto actualizado !.',
                    showConfirmButton: false,
                    timer: 3000,
                })
           },
            deleteConfirmation(){
                var self = this
                    Swal.fire({
                    text: '¿ Querés remover este producto del carro ?.',
                    type: 'warning',
                    confirmButtonText: 'Ok',
                    showCancelButton: true,
                    cancelButtonText: 'Cancelar',
                }).then(function (isConfirm) {
                    if (isConfirm.value) {
                        self.removeFromCart()
                    }
                })
            },
            async removeFromCart(){
                 await this.$store.commit('cart/removeItem', this.item.id)
                  this.total = this.getItemTotal(this.currentItem)
           },
           
        }
    }
</script>
<style>