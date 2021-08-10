# Route Injection Bindings

Inspired by Laravel's [Route Model Binding](https://laravel.com/docs/8.x/routing#route-model-binding), this package takes that a step further, and allows for Controller method injection of *any* object type.

1. Activate the `RouteInjection\Providers]RouteInjectionServiceProvider` by either adding it to your `app.php` config, or making sure your `composer.json` file includes the `@php artisan package:discover --ansi` script.
2. Create a class designed to parse an incoming `Request` and produce a concrete object that will be injected into the controller. To do this, simply extend the `RouteInjection\Binder` class.
3. Reference your custom `Binder` class name in the `route-injection` config array.
4. Ensure that your routes leverage Laravel's `SubstituteBindings` middleware. This should be automatic in your 'api' and 'web' routes, but any outside of these groups may need to have it explicitly listed among its middleware configs.