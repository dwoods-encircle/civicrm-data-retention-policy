{include file="CRM/common/formButtons.tpl" location="top"}
<div class="crm-block crm-form-block">
  {foreach from=$entityDefinitions key=key item=definition}
    <div class="crm-section">
      <div class="label">{$form.$key.label}</div>
      <div class="content">
        {$form.$key.html}
        <div class="description">{$definition.description}</div>
      </div>
      <div class="clear"></div>
    </div>
  {/foreach}
</div>
{include file="CRM/common/formButtons.tpl" location="bottom"}
