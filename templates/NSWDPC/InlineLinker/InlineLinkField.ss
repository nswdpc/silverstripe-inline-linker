<%-- this is the admin TabSet.ss template --%>
<div $getAttributesHTML("class") class="ss-tabset $extraClass">

    <% if $Title %>
        <div class="form-group">
            <label class="form__field-label" for="{$HolderID}-holder">{$Title.XML}</label>
        </div>
    <% end_if %>

	<ul class="nav nav-tabs">
	  <% loop $Tabs %>
		<li class="$FirstLast $MiddleString $extraClass nav-item">
			<a href="#$id" id="tab-$id" class="nav-link">$Title</a>
		</li>
	  <% end_loop %>
	</ul>

	<div class="tab-content">
	  <% loop $Tabs %>
		  <% if $Tabs %>
			$FieldHolder
		  <% else %>
			<div $getAttributesHTML("class") class="tab-pane $extraClass">
				<% loop $Fields %>
					$FieldHolder
				<% end_loop %>
			</div>
		  <% end_if %>
	  <% end_loop %>
	</div>

</div>