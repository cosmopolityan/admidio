
<div id="{$id}_group" class="form-group row {if $property eq 1}admidio-form-group-required{/if}">
    <label for="{$id}" class="col-sm-3 control-label">
        {include file='sys-template-parts/parts/form.part.icon.tpl'}
        {$label}
        {include file='sys-template-parts/parts/form.part.iconhelp.tpl'}
    </label>
    <div class="{if $data.formtype neq 'vertical' and $data.formtype neq 'navbar'}col-sm-9{/if}">
        <select name="{$id}" id="{$id}" {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}" {/foreach}>
            {assign "group" ""}
            {foreach $values as $optionvar}
                {if array_key_exists("group", $optionvar) && $optionvar["group"] neq $group}
                    {if $group neq ""}</optgroup>{/if}
                    <optgroup label="{$optionvar["group"]}">
                    {assign "group" "{$optionvar["group"]}"}
                {/if}
                <option value="{$optionvar["id"]}" {if $defaultValue eq $optionvar["id"]}selected="selected"{/if}>{$optionvar["value"]}</option>
            {/foreach}
            {if $group neq ""}</optgroup>{/if}
        </select>

        {include file='sys-template-parts/parts/form.part.helptext.tpl'}
        {include file='sys-template-parts/parts/form.part.warning.tpl'}
    </div>
</div>
