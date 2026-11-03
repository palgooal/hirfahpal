{{--
    VQA-FIX-02: the current query parameters (except change-locale) as hidden inputs of a language switcher's GET form,
    which would otherwise replace them with its own fields: the product's slug, browse filters, search and sorting
    survive the switch. One input per raw pair, so repeated keys (category=a&category=b) are kept.
--}}
@foreach (\App\Http\Middleware\SetLocale::queryPairsWithoutSwitch(request()) as $pair)
    @php
        [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
    @endphp
    <input type="hidden" name="{{ urldecode($name) }}" value="{{ urldecode($value) }}">
@endforeach
