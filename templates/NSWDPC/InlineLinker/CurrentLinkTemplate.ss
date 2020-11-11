<div class="fill-width uploadfield-item">

    <% if $Type == 'File' && $File %>
        <% with $File %>
            <% if $IsImage %>
                <div class="uploadfield-item__thumbnail" style="background-image: url('{$CMSThumbnail.URL}');"></div>
            <% else %>
                <div class="uploadfield-item__thumbnail" style="background-image: url('{$PreviewLink}');"></div>
            <% end_if %>
        <% end_with %>
    <% else %>
        <div class="uploadfield-item__thumbnail"></div>
    <% end_if %>

    <div class="uploadfield-item__details fill-height flexbox-area-grow">
        '{$Title}' ({$LinkURL} - {$Type})
    </div>

 </div>
