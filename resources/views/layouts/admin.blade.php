@php
    $__layoutTitle = trim((string) $__env->yieldContent('title'));
    $__layoutSubtitle = trim((string) $__env->yieldContent('subtitle'));
    $__layoutActions = trim((string) $__env->yieldContent('actions'));
    $__layoutBreadcrumb = trim((string) $__env->yieldContent('breadcrumb'));
    $__layoutContent = (string) $__env->yieldContent('content');
    $__layoutPageClass = trim((string) $__env->yieldContent('page-class'));
@endphp

<x-admin.layout
    nav="admin"
    :title="$__layoutTitle"
    :subtitle="$__layoutSubtitle !== '' ? $__layoutSubtitle : null"
    :breadcrumb="$__layoutBreadcrumb"
    :actions="$__layoutActions !== '' ? $__layoutActions : null"
    :page-class="$__layoutPageClass !== '' ? $__layoutPageClass : null"
>
    {!! $__layoutContent !!}
</x-admin.layout>
