<div class="col-md-12">
  <div class="form-group">
    <h2>OGOship</h2>
    <div class="checkbox">
        <label class="form-control-label">
        <input type="checkbox" name="export_to_ogoship" value="1"  {if $product->export_to_ogoship!=0} checked="checked" {/if}/>
        {l s='Do not export to OGOship'}
        </label>
    </div>
  </div>
</div>
