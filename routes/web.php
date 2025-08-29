<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Http\Request;

use \App\Http\Controllers\HomeController;
use \App\Http\Controllers\Auth\LoginController;
use \App\Http\Controllers\Auth\RegisterController;

use \App\Http\Controllers\Admin\AdminController;
use \App\Http\Controllers\Admin\CategoryController;

use \App\Http\Controllers\Admin\GachaController;
use \App\Http\Controllers\Admin\LostProductController;

use \App\Http\Controllers\Admin\DpController;

use \App\Http\Controllers\User\UserController;
use \App\Http\Controllers\Admin\DeliveryController;
use \App\Http\Controllers\Admin\SettingController;


use \App\Http\Controllers\PaymentController;
use \App\Http\Controllers\TestController;

use \App\Http\Controllers\Admin\CouponController;
use \App\Http\Controllers\RankController;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

use \App\Http\Controllers\LineAccountController;
use \App\Http\Controllers\Admin\PlanController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/', function () {
//     return Inertia::render('Welcome', [
//         'canLogin' => Route::has('login'),
//         'canRegister' => Route::has('register'),
//         'laravelVersion' => Application::VERSION,
//         'phpVersion' => PHP_VERSION,
//     ]);
// });

// Route::get('/dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->post('/line/disconnect', [LineAccountController::class, 'disconnect'])->name('line.disconnect');

Route::get('/line/confirm', [LineAccountController::class, 'confirm'])->name('line.comfirm');
Route::middleware('auth')->get('/line/verify', [LineAccountController::class, 'confirm_in_site'])->name('line.comfirm_in_site');

Route::get('/dashboard', [HomeController::class, 'dashboard'])->name('dashboard');

Route::get('/', [HomeController::class, 'index'])->name('main');
Route::get('/dp', [HomeController::class, 'dp'])->name('main.dp');

//Route::get('/how_to_use', [HomeController::class, 'how_to_use'])->name('main.how_to_use');
Route::get('/privacy_police', [HomeController::class, 'privacy_police'])->name('main.privacy_police');
Route::get('/terms_conditions', [HomeController::class, 'terms_conditions'])->name('main.terms_conditions');
//Route::get('/contact_us', [HomeController::class, 'contact_us'])->name('main.contact_us');
Route::get('/notation_commercial', [HomeController::class, 'notation_commercial'])->name('main.notation_commercial');

Route::get('register', [RegisterController::class, 'create'])->name('register'); 
Route::post('/register/send_phone', [RegisterController::class, 'send'])->name('register.phone.send');
Route::post('/register/verify_phone', [RegisterController::class, 'verify'])->name('register.phone.verify');
Route::post('/register/send', [RegisterController::class, 'send_email'])->name('register.email.send');
Route::post('/register/verify', [RegisterController::class, 'verify_email'])->name('register.email.verify');

Route::post('/email/verify', [LoginController::class, 'verify_email'])->name('email.verify');

Route::post('/register/register', [RegisterController::class, 'register'])->name('register.phone.register');

Route::post('register', [RegisterController::class, 'store']);
Route::middleware('auth')->get('register/complete', [RegisterController::class, 'complete'])->name('register.complete');

Route::get('login', [LoginController::class, 'create'])->name('login');

Route::post('login', [LoginController::class, 'store'])->name('login_post');
Route::get('logout', [LoginController::class, 'destroy'])->name('logoutGet'); 

Route::get('maintainance', [HomeController::class, 'maintainance'])->name('maintainance'); 
Route::get('/gacha/{id}', [UserController::class, 'gacha'])->name('user.gacha');

Route::get('/rank', [UserController::class, 'profile'])->name('profile');

Route::group(['prefix'=>'user', 'middleware' => 'user-access:user'], function(){
    Route::get('/', [UserController::class, 'index'])->name('user');
    Route::get('/dp_detail/{id}', [UserController::class, 'dp_detail'])->name('user.dp.detail');
    Route::post('/dp_detail/post', [UserController::class, 'dp_detail_post'])->name('user.dp.detail.post');
    Route::get('/dp/success', [UserController::class, 'dp_detail_success'])->name('user.dp.detail.success');


    Route::get('/dpexchange', [UserController::class, 'dpexchange'])->name('dpexchange');
    
    Route::post('/gacha/start', [UserController::class, 'start'])->name('user.gacha.start_post');
    Route::get('/gacha/video/{token}', [UserController::class, 'video'])->name('user.gacha.video');
    Route::get('/gacha/result/{token}', [UserController::class, 'result'])->name('user.gacha.result'); 

    Route::post('/gacha/result/exchange', [UserController::class, 'result_exchange'])->name('user.gacha.result.exchange');
    Route::get('/gacha/end/{token}', [UserController::class, 'gacha_end'])->name('user.gacha.end');
    
    Route::get('/products', [UserController::class, 'products'])->name('user.products');
    Route::post('/products/point/exchange', [UserController::class, 'product_point_exchange'])->name('user.products.point.exchange');
    Route::post('/products/delivery/post', [UserController::class, 'product_delivery_post'])->name('user.delivery.post');
    
    Route::get('/products/wait', [UserController::class, 'delivery_wait'])->name('user.products.wait');
    Route::get('/products/delivered', [UserController::class, 'delivered'])->name('user.products.delivered');

    Route::get('/point', [UserController::class, 'point'])->name('user.point'); 
    Route::get('/point/purchase/{id}', [PaymentController::class, 'purchase'])->name('user.point.purchase');
    Route::post('/point/card_payment', [PaymentController::class, 'card_payment_post'])->name('user.point.card_payment_post');
    Route::get('/point/card_register', [PaymentController::class, 'card_register'])->name('user.point.card_register');
    Route::get('/point/card_payment/{id}', [PaymentController::class, 'card_payment'])->name('user.point.card_payment');
    Route::post('/point/deleteCard', [PaymentController::class, 'deleteCard'])->name('user.point.deleteCard');
    Route::post('/point/purchase_bank', [PaymentController::class, 'purchase_bank'])->name('user.point.purchase_bank');
    Route::post('/point/purchase/paymentRegister', [PaymentController::class, 'paymentRegister'])->name('user.point.paymentRegister');
    Route::post('/point/purchase/paymentExecute', [PaymentController::class, 'paymentExecute'])->name('user.point.paymentExecute');
    Route::get('/point/purchase_success', [UserController::class, 'purchase_success'])->name('purchase_success');
    
    Route::get('/favorite', [UserController::class, 'favorite'])->name('user.favorite');
    Route::post('/favorite/add', [UserController::class, 'favorite_add'])->name('user.favorite.add');

    Route::get('/address', [UserController::class, 'address'])->name('user.address');
    Route::post('/address/post', [UserController::class, 'address_post'])->name('user.address.post');

    Route::get('/coupon', [UserController::class, 'coupon'])->name('user.coupon');
    Route::post('/coupon/post', [UserController::class, 'coupon_post'])->name('user.coupon.post');

    Route::get('/error', [UserController::class, 'noProduct'])->name('user.error');

});

Route::group(['prefix'=>'admin', 'middleware' => 'user-access:admin'], function(){
    Route::get('/', [AdminController::class, 'index'])->name('admin'); 

    Route::get('/category', [CategoryController::class, 'category'])->name('admin.category');
    Route::get('/category/create',[CategoryController::class,'category_create'])->name('admin.category.create');
    Route::post('/category/store',[CategoryController::class,'category_store'])->name('admin.category.store');
    Route::post('/category/sorting_store', [CategoryController::class, 'sorting_store'])->name('admin.category.sorting.store'); 
    Route::delete('/category/destroy/{id}', [CategoryController::class, 'category_destroy'])->name('admin.category.destroy');

    Route::get('/point', [AdminController::class, 'point_list'])->name('admin.point');
    Route::get('/point/create', [AdminController::class, 'point_create'])->name('admin.point.create'); 
    Route::get('/point/edit/{id}', [AdminController::class, 'point_edit'])->name('admin.point.edit');
    Route::post('/point/store', [AdminController::class, 'point_store'])->name('admin.point.store');
    Route::post('/point/update', [AdminController::class, 'point_update'])->name('admin.point.update');
    Route::delete('/point/destroy/{id}',[AdminController::class,'point_destroy'])->name('admin.point.destroy');

    Route::get('/delivery', [DeliveryController::class, 'admin'])->name('admin.delivery');
    Route::get('/delivery/acquired', [DeliveryController::class, 'acquired'])->name('admin.delivery.acquired');
    Route::post('/delivery/product_data', [DeliveryController::class, 'getProductData'])->name('admin.delivery.product_data');
    Route::post('/delivery/post', [DeliveryController::class, 'deliver_post'])->name('admin.delivery.post');
    Route::get('/delivery/completed', [DeliveryController::class, 'completed'])->name('admin.delivery.completed');
    Route::post('/delivery/un_delivery', [DeliveryController::class, 'unDeliver_post'])->name('admin.delivery.un_delivery');
    Route::get('/delivery/csv', [DeliveryController::class, 'csv_delivery'])->name('admin.deliver.csv');
    Route::post('/delivery/csv_post', [DeliveryController::class, 'csv_delivery_post'])->name('admin.delivery.csv_post');
    
    Route::get('/videos', [AdminController::class, 'videos'])->name('admin.video');
    Route::post('/videos/store', [AdminController::class, 'video_store'])->name('admin.video.store');
    Route::delete('/videos/destroy/{id}',[AdminController::class,'video_destroy'])->name('admin.video.destroy');

    Route::get('/coupon', [CouponController::class, 'index'])->name('admin.coupon');
    Route::get('/coupon/create', [CouponController::class, 'create'])->name('admin.coupon.create');
    Route::get('/coupon/edit/{id}', [CouponController::class, 'edit'])->name('admin.coupon.edit');
    Route::post('/coupon/store', [CouponController::class, 'store'])->name('admin.coupon.store');
    Route::delete('/coupon/delete/{id}', [CouponController::class, 'delete'])->name('admin.coupon.delete');

    Route::get('/banner', [AdminController::class, 'banner'])->name('admin.banner');
    Route::post('/banner/create',[AdminController::class,'banner_create'])->name('admin.banner.create');
    Route::post('/banner/store',[AdminController::class,'banner_store'])->name('admin.banner.store');
    Route::delete('/banner/destroy/{id}', [AdminController::class, 'banner_destroy'])->name('admin.banner.destroy');

    Route::get('/dp', [DpController::class, 'index'])->name('admin.dp');
    Route::get('/dp/create', [DpController::class, 'create'])->name('admin.dp.create');
    Route::get('/dp/edit/{id}', [DpController::class, 'edit'])->name('admin.dp.edit');
    Route::post('/dp/store', [DpController::class, 'store'])->name('admin.dp.store');
    Route::post('/dp/update', [DpController::class, 'update'])->name('admin.dp.update');
    Route::delete('/dp/destroy/{id}',[DpController::class,'destroy'])->name('admin.dp.destroy');

    Route::get('/settings', [SettingController::class, 'index'])->name('admin.settings');
    Route::post('/settings/maintenance_store', [SettingController::class, 'maintenance_store'])->name('admin.settings.maintenance_store');
    Route::post('/settings/payment_store', [SettingController::class, 'payment_store'])->name('admin.settings.payment_store');

    Route::get('/bank_payments', [PaymentController::class, 'bank_payments'])->name('admin.bank_payment');
    Route::post('/update_bank_payments', [PaymentController::class, 'update_bank_payments'])->name('admin.update_bank_payment');
    Route::post('/bank_payments', [PaymentController::class, 'get_bank_payments'])->name('admin.bank_payments');
    // Test Payment
    Route::get('/test/point', [UserController::class, 'point'])->name('test.user.point');
    Route::get('/test/point/purchase/{id}', [PaymentController::class, 'purchase'])->name('test.user.point.purchase');
    Route::get('/test/point/card_payment/{id}', [PaymentController::class, 'card_payment'])->name('test.user.point.card_payment');
    Route::post('/test/point/card_payment', [PaymentController::class, 'card_payment_post'])->name('test.user.point.card_payment_post');
    Route::get('/test/point/card_register', [PaymentController::class, 'card_register'])->name('test.user.point.card_register');
    Route::post('/test/point/deleteCard', [PaymentController::class, 'deleteCard'])->name('test.user.point.deleteCard');
    Route::post('/test/point/purchase_bank', [PaymentController::class, 'purchase_bank'])->name('test.user.point.purchase_bank');
    Route::post('/test/point/purchase/paymentRegister', [PaymentController::class, 'paymentRegister'])->name('test.user.point.paymentRegister');
    Route::post('/test/point/purchase/paymentExecute', [PaymentController::class, 'paymentExecute'])->name('test.user.point.paymentExecute');
    Route::get('/test/point/purchase_success', [UserController::class, 'purchase_success'])->name('test.purchase_success');

    Route::get('/pages/edit', [AdminController::class, 'page_edit'])->name('admin.page.edit');
    Route::post('/pages/update', [AdminController::class, 'page_update'])->name('admin.page.update');

    // user management
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/users/detail/{id}', [AdminController::class, 'user_detail'])->name('admin.users.detail');
    Route::get('/users/purchase_log/{id}', [AdminController::class, 'purchase_log'])->name('admin.users.purchase_log');
    Route::get('/users/gacha_log/{id}', [AdminController::class, 'gacha_log'])->name('admin.users.gacha_log');
    Route::post('/users/update', [AdminController::class, 'user_update'])->name('admin.users.update');
    Route::post('/users/add_point', [AdminController::class, 'user_add_point'])->name('admin.users.add_point');
    Route::post('/users/clear_history', [AdminController::class, 'clear_history'])->name('admin.users.clear_history');

    Route::get('/admin/users/export', [AdminController::class, 'exportUsers'])->name('admin.users.export');

    Route::get('/rank', [RankController::class, 'index'])->name('admin.rank');
    Route::post('/rank/store', [RankController::class, 'store'])->name('admin.rank.store');
    Route::delete('/rank/destroy/{id}', [RankController::class, 'destroy'])->name('admin.rank.destroy'); 

});

Route::group(['prefix'=>'admin', 'middleware' => 'user-access:staff'], function(){
    Route::get('/gacha', [GachaController::class, 'index'])->name('admin.gacha');
    Route::get('/gacha/create', [GachaController::class, 'create'])->name('admin.gacha.create');
    Route::post('/gacha/store', [GachaController::class, 'store'])->name('admin.gacha.store');
    Route::get('/gacha/edit/{id}', [GachaController::class, 'edit'])->name('admin.gacha.edit');
    Route::post('/gacha/update', [GachaController::class, 'update'])->name('admin.gacha.update'); 

    Route::post('/gacha/copy', [GachaController::class, 'copy'])->name('admin.gacha.copy');
    
    Route::post('/gacha/product_last/create', [GachaController::class, 'product_last_create'])->name('admin.gacha.product_last.create');
    Route::delete('/gacha/product_last/destroy/{id}',[GachaController::class,'product_last_destroy'])->name('admin.gacha.product_last.destroy');
    Route::post('/gacha/product/create', [GachaController::class, 'product_create'])->name('admin.gacha.product.create');

    Route::post('/gacha/to_public', [GachaController::class, 'to_public'])->name('admin.gacha.to_public'); 
    Route::post('/gacha/shuffle_mode', [GachaController::class, 'shuffle_mode'])->name('admin.gacha.shuffle_mode'); 
    Route::delete('/gacha/destroy/{id}', [GachaController::class, 'destroy'])->name('admin.gacha.destroy'); 
    
    Route::get('/gacha/sorting', [GachaController::class, 'sorting'])->name('admin.gacha.sorting');
    Route::post('/gacha/sorting/store', [GachaController::class, 'sorting_store'])->name('admin.gacha.sorting.store'); 

    Route::post('/gacha/upload_csv', [GachaController::class, 'upload_csv'])->name('admin.gacha.upload_csv');
    
    Route::get('/lost_product', [LostProductController::class, 'index'])->name('admin.lost_product');
    Route::post('/lost_product/create', [LostProductController::class, 'create'])->name('admin.lost_product.create');
});

require __DIR__.'/auth.php';
