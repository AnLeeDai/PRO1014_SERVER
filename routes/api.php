<?php

return [
  'post-login'                         => ['POST' => 'AuthController@handleLogin'],
  'post-register'                      => ['POST' => 'AuthController@handleRegister'],
  'post-forgot-password'               => ['POST' => 'AuthController@handleForgotPassword'],
  'post-change-password'               => ['POST' => 'AuthController@handleChangePassword'],
  'get-admin-password-requests'        => ['GET'  => 'AuthController@listPendingPasswordRequests'],
  'post-admin-process-password-request' => ['POST' => 'AuthController@handleAdminPasswordRequestAction'],

  'get-category'       => ['GET'  => 'CategoryController@handleListCategories'],
  'post-category'      => ['POST' => 'CategoryController@handleCreateCategory'],
  'put-category'       => ['PUT'  => 'CategoryController@handleUpdateCategory'],
  'post-hide-category' => ['POST' => 'CategoryController@handleHideCategory'],
  'post-unhide-category' => ['POST' => 'CategoryController@handleUnhideCategory'],

  'get-users'            => ['GET'  => 'UserController@handleListUsers'],
  'get-user-by-id'       => ['GET'  => 'UserController@handleGetUserById'],
  'put-user'             => ['PUT'  => 'UserController@handleUpdateUserProfile'],
  'post-avatar'          => ['POST' => 'UserController@handleUpdateAvatar'],
  'post-deactivate-user' => ['POST' => 'UserController@handleDeactivateUser'],
  'post-reactivate_user' => ['POST' => 'UserController@handleReactivateUser'],

  'get-banners'         => ['GET'    => 'BannerController@handleListBannersPaginated'],
  'post-banner'         => ['POST'   => 'BannerController@handleCreateBanner'],
  'post-update-banner'  => ['POST'   => 'BannerController@handleUpdateBanner'],
  'delete-banner'       => ['DELETE' => 'BannerController@handleDeleteBanner'],

  'post-discount'                    => ['POST'   => 'DiscountController@handleCreateDiscount'],
  'get-discounts'                    => ['GET'    => 'DiscountController@handleListDiscounts'],
  'delete-discount'                  => ['DELETE' => 'DiscountController@handleDeleteDiscount'],
  'get-available-discounts-for-product' => ['GET'  => 'DiscountController@handleGetAvailableDiscountsForProduct'],
  'post-remove-discount-usage'       => ['POST'   => 'DiscountController@handleRemoveDiscountUsage'],

  'post-product'      => ['POST' => 'ProductController@handleCreateProduct'],
  'get-products'      => ['GET'  => 'ProductController@handleListProducts'],
  'get-product-by-id' => ['GET'  => 'ProductController@handleGetProductById'],
  'post-edit-product' => ['POST' => 'ProductController@handleUpdateProduct'],
  'post-hide-product' => ['POST' => 'ProductController@handleHideProduct'],
  'post-unhide-product' => ['POST' => 'ProductController@handleUnhideProduct'],

  'post-cart'        => ['POST'   => 'CartController@handleAddToCart'],
  'get-cart'         => ['GET'    => 'CartController@handleGetCartItems'],
  'put-cart'         => ['PUT'    => 'CartController@handleUpdateCartItem'],
  'delete-cart-item' => ['DELETE' => 'CartController@handleDeleteCartItem'],

  'post-checkout'                 => ['POST' => 'OrderController@handleCheckout'],
  'get-order-history'             => ['GET'  => 'OrderController@handleGetOrderHistory'],
  'post-admin-update-order-status' => ['POST' => 'OrderController@handleAdminUpdateOrderStatus'],
  'get-admin-orders'              => ['GET'  => 'OrderController@handleAdminListOrdersPaginated'],
];
