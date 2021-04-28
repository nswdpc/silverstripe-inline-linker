
    <%-- this is not used in elemental when inline_editable is true for the element --%>
    <div class="form-group field inlinelinkcomposite" id="{$HolderID}">

        <label class="form__field-label" for="{$HolderID}-holder">
            <% if $Title %>
                {$Title.XML}
            <% else %>
                Link
            <% end_if %>
        </label>

        <div class="form__field-holder" id="{$HolderID}-holder">
            <div class="inlinelink-wrapper">
                {$Field}
            </div>
        </div>

        <% if $RightTitle %><p class="form__field-extra-label" id="extra-label-$ID">{$RightTitle.XML}</p><% end_if %>

    </div>
