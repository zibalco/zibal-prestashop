
<!-- Zibal Payment Module -->
<p class="payment_module">
    <a href="javascript:$('#zibal_prestashop').submit();" title="{l s='Online payment with zibal_prestashop' mod='zibal_prestashop'}">
        <img src="modules/zibal_prestashop/zibal.png" alt="{l s='Online payment with zibal_prestashop' mod='zibal_prestashop'}" />
		{l s=' پرداخت با کارتهای اعتباری / نقدی بانک های عضو شتاب توسط دروازه پرداخت زیبال ' mod='zibal_prestashop'}
<br>
</a></p>

<form action="modules/zibal_prestashop/zibal.php?do=payment" method="post" id="zibal_prestashop" class="hidden">
    <input type="hidden" name="orderId" value="{$orderId}" />
</form>
<br><br>
<!-- End of Zibal Payment Module-->
