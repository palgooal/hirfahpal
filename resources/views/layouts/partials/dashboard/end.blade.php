<!-- [Page Specific JS] start
    {{-- <script src="{{asset('assets-dashboard/js/plugins/apexcharts.min.js')}}"></script>--> --}}
<!-- custom widgets js -->
    {{-- <script src="{{asset('assets-dashboard/js/widgets/all-earnings-graph.js')}}"></script> --}}
    {{-- <script src="{{asset('assets-dashboard/js/widgets/page-views-graph.js')}}"></script> --}}
    {{-- <script src="{{asset('assets-dashboard/js/widgets/total-task-graph.js')}}"></script> --}}
    {{-- <script src="{{asset('assets-dashboard/js/widgets/download-graph.js')}}"></script> --}}
    {{-- <script src="{{asset('assets-dashboard/js/widgets/customer-rate-graph.js')}}"></script> --}}
    {{-- <script src="{{asset('assets-dashboard/js/widgets/tasks-graph.js')}}"></script> --}}
    {{-- <script src="{{asset('assets-dashboard/js/widgets/total-income-graph.js')}}"></script>--> --}}
<!-- [Page Specific JS] end -->
{{-- Required Js --}}
<script src="{{asset('assets-dashboard/js/plugins/bootstrap-slider.min.js')}}"></script>
<script src="{{asset('assets-dashboard/js/plugins/jquery-3.7.1.min.js')}}"></script>
<script src="{{asset('assets-dashboard/js/plugins/simplebar.min.js')}}"></script>
<script src="{{asset('assets-dashboard/js/plugins/popper.min.js')}}"></script>
<script src="{{asset('assets-dashboard/js/icon/custom-icon.js')}}"></script>
<script src="{{asset('assets-dashboard/js/plugins/feather.min.js')}}"></script>
<script src="{{asset('assets-dashboard/js/component.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
{{-- Able Pro script.js is not loaded: resources/js/admin-dashboard.js drives the sidebar and provides
     the helpers component.js used from it (AD-UI-01C-4B). Its localStorage layout switch no longer runs. --}}
{{-- Able Pro theme.js is not loaded either (AD-UI-01C-4C): the admin uses the light HIRFAH identity only.
     The <html> attributes it used to set (lang, dir, data-pc-direction, data-pc-theme, data-pc-layout,
     data-pc-sidebar-caption, the preset-1 admin-shell classes) are rendered by the server in head.blade.php,
     so a saved localStorage theme or layout can no longer change the dashboard. --}}


@stack('scripts')


@stack('modals')

</body>

</html>
