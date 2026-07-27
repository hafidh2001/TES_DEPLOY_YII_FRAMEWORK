    
<div color-picker <?= $this->expandAttributes($this->options) ?>>

    <!-- label -->
    <?php if ($this->label != ""): ?>
        <label <?= $this->expandAttributes($this->
		labelOptions) ?>
            class="<?= $this->labelClass ?>" for="<?= $this->renderID; ?>">
                <?= $this->label ?> <?php if ($this->isRequired()) : ?> <div class="required">*</div> <?php endif; ?>
        </label>
    <?php endif; ?>
    <!-- /label -->

    <div class="<?= $this->fieldColClass ?>">
        <!-- data -->
        <data name="name" class="hide"><?= $this->name ?></data>
		<data name="value" class="hide"><?=$this->color ?></data>
        <data name="model_class" class="hide"><?= Helper::getAlias($model) ?></data>
        <!-- /data -->

		<div class="input-group colorpicker">
        <!-- field -->
			<span class="input-group-addon"><i style="background-color: {{color}};"></i></span>
			<input type="<?= $this->fieldType ?>" <?= $this->expandAttributes($this->fieldOptions) ?>
                   ng-model="color" value="<?= $this->color ?>"/>
		
        <!-- /field -->
		</div>

        <!-- error -->
        <div ng-if="errors[name]" class="alert error alert-danger">
            {{ errors[name][0]}}
        </div>
        <!-- /error -->
    </div>

	<?php 
		
		$path = Yii::app()->baseUrl . '/plansys/components/ui/FormFields/ColorPicker';
		
		foreach($this->includeCSS() as $css) {
			echo "<link rel='stylesheet' href='$path/$css' />";
		}
	?>
</div>