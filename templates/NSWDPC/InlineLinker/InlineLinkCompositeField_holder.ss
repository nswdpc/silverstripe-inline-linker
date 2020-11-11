<div class="form-group field inlinelinkcomposite" id="{$HolderID}">

    <% if $Title %>
        <label class="form__field-label" for="{$HolderID}-holder">$Title</label>
    <% end_if %>

    <div class="form__field-holder" id="{$HolderID}-holder">
        <div class="inlinelink-wrapper">
            {$Field}
        </div>
    </div>

</div>
