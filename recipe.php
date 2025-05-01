<?php
session_start();
include 'connect.php';

// Get recipe ID from URL
$recipeID = $_GET['id'];

// Sanitize input
$recipeID = $mysqli->real_escape_string($recipeID);

// Basic recipe info query
$sql = "SELECT  r.RecipeID, 
				r.fldRecipeName, 
				r.fldRecipeImage, 
				r.fldPrepTime, 
				r.fldCookTime, 
				r.fldDescription, 
				r.fldYield, 
				r.fldYieldUnit,
				r.fldNutrition, 
				c.fldCuisine 
        FROM tblRecipes r 
        LEFT JOIN tblCuisines c ON r.fkCuisineID = c.pkCuisineID 
        WHERE r.RecipeID = '$recipeID'";
$result = $mysqli->query($sql);
if (!$result) {
    echo "Error: " . $mysqli->error;
    exit;
}

if ($result->num_rows > 0) {
    $recipe = $result->fetch_assoc();
} else {
    header("Location:/index.php");
    exit;
}

// Ingredients query and array storage
$ingredientsSql = "SELECT 
						CASE 
							WHEN ri.fldType = 1 THEN i.fldIngredientName
							WHEN ri.fldType = 2 THEN r.fldRecipeName
							WHEN ri.fldType = 3 THEN ri.fldPrepNotes
						END AS ComponentName,
						ri.pkRecipeIngredientID,
						ri.fldType as Type,
						ri.fkComponentID,
						ri.fldQuantity,
						iu.pkUnitID,
						iu.fldUnit,
						iu.fldAbbreviation,
						iu.fldPlural,
						ri.fldPrepNotes
					FROM tblRecipeIngredients ri
					LEFT JOIN tblIngredients i ON ri.fldType = 1 AND ri.fkComponentID = i.pkIngredientID
					LEFT JOIN tblRecipes r ON ri.fldType = 2 AND ri.fkComponentID = r.RecipeID
					LEFT JOIN tblIngredientUnits iu ON ri.fkUnitID = iu.pkUnitID
					WHERE ri.fkRecipeID = '$recipeID'
					ORDER BY ri.fldIngredientOrder ASC;
					";
$ingredientsResult = $mysqli->query($ingredientsSql);
$ingredients = [];
while($ingredient = $ingredientsResult->fetch_assoc()) {
    $ingredients[] = $ingredient;
}


// Instructions query and array storage
$instructionsSql = "SELECT fldInstructions 
                    FROM tblRecipeInstructions 
                    WHERE fkRecipeID = '$recipeID' 
                    ORDER BY fldStepNumber ASC";
$instructionsResult = $mysqli->query($instructionsSql);
$instructions = [];
while($instruction = $instructionsResult->fetch_assoc()) {
    $instructions[] = $instruction;
}

// Tips query and array storage
$tipsSql = "SELECT fldRecipeTip 
            FROM tblRecipeTips 
            WHERE fkRecipeID = '$recipeID' 
            ORDER BY fldTipOrder ASC";
$tipsResult = $mysqli->query($tipsSql);
$tips = [];
while($tip = $tipsResult->fetch_assoc()) {
    $tips[] = $tip;
}

// Tags query and array storage
$tagsSql = "SELECT rt.fldTagName, rt.pkTagNameID FROM tblRecipeTags t 
            JOIN tblTagOptions rt ON t.fkTagID = rt.pkTagNameID 
            WHERE t.fkRecipeID = '$recipeID'";
$tagsResult = $mysqli->query($tagsSql);
$tags = [];
while($tag = $tagsResult->fetch_assoc()) {
    $tags[] = $tag;
}


//Format ingredient quantity
function formatQuantity($number) {
    // Convert to float to handle string inputs
    $num = floatval($number);
    
    // Handle specific fractions with proper fraction characters
    switch (number_format($num, 2)) {
        case "0.125": return "⅛"; 
        case "0.25": return "¼"; 
        case "0.33": return "⅓"; 
        case "0.50": return "½"; 
        case "0.66": return "⅔"; 
        case "0.75": return "¾"; 
    }
    
    // If it's a whole number, return as integer
    if($num == floor($num)) {
        return number_format($num, 0);
    }
    
    // For any other decimals, return with up to 2 decimal places
    return rtrim(rtrim(number_format($num, 2), '0'), '.');
}

function formatTime($minutes) {
    if (!$minutes) return "0 minutes";
    
    if ($minutes >= 60) {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        if ($mins == 0) {
            return $hours . "hr" . ($hours > 1 ? "s" : "");
        }
        return $hours . "hr" . ($hours > 1 ? "s" : "") . " " . $mins . " min";
    }
    
    return $minutes . "min";
}





/////////////////////////////////
////////// BEGIN HTML ///////////
/////////////////////////////////
require_once('utility/public_head.php');
include 'utility/nav.php';
?>
	
	<div class="RecipeContainer">
		<div class="RecipeHeader">
			<div id="RecipeInfo">
				<h1><?php echo htmlspecialchars($recipe['fldRecipeName']); ?></h1>
				<div class="cuisine">
					<span><?php echo strtoupper(htmlspecialchars($recipe['fldCuisine'])); ?></span>
				</div>
				<div id="recipeMeta">
					<p id="recipeDescription"><?php echo htmlspecialchars($recipe['fldDescription']); ?></p>
					<p id="timeContainer">
						<?php if ($recipe['fldPrepTime']): ?>
							<span class="recipeTime"><b>PREP:</b> <?php echo formatTime($recipe['fldPrepTime']); ?></span>
						<?php endif; ?>
						
						<?php if ($recipe['fldCookTime']): ?>
							<span class="recipeTime"><b>COOK:</b> <?php echo formatTime($recipe['fldCookTime']); ?></span>
						<?php endif; ?>
						
					</p>
					
				</div>
			</div>
			
			<div class="recipe-image-container">
				<?php if($recipe['fldRecipeImage']): ?>
					<img class="recipe-image" src="/images/recipes/<?php echo htmlspecialchars($recipe['fldRecipeImage']); ?>" 
						 alt="<?php echo htmlspecialchars($recipe['fldRecipeName']); ?>">
				<?php endif; ?>
			</div>
		</div>
	
	
		 <div class="recipe-content">
			
			
			
			
			
			<?php
			//Are any of this recipe's ingredients already in the shopping list?
			echo '<div id="ingredients" ';
			if ( isset($_SESSION['ShoppingList']) && array_key_exists($recipeID, $_SESSION['ShoppingList']) ) {
				// Extract Ingredient IDs for the recipe that are in the current shopping list
				$ExistingListIDs = array_keys($_SESSION['ShoppingList'][$recipeID]);
				echo 'class="ActiveList"';
				$ActiveList=True;	
			}
			echo '">';
			?>
			
				<h2>Ingredients
							<?php if($recipe['fldYield']>0):?>
					<span id="Yield" data-originalyield="<?php echo htmlspecialchars(formatQuantity($recipe['fldYield'])); ?>">
						<b>for </b>
						<span id="YieldQuantity"><?php echo htmlspecialchars(formatQuantity($recipe['fldYield'])); ?></span>
						<span id="YieldUnit"><?php echo htmlspecialchars($recipe['fldYieldUnit']); ?></span>
					</span>
	
				<?php endif;?>
				</h2>
	
							
				<?php				
				foreach($ingredients as $ingredient){
					if(in_array($ingredient['Type'], [1, 2])){
						echo'<p class="ingredient';
						
							//Is this ingredient in the shopping list already?
							if ( isset($ActiveList) && in_array($ingredient['pkRecipeIngredientID'], $ExistingListIDs) ) {
								echo " InList";
							}
							echo '"';//closing quote for class						
							echo'
								data-recipeid="'.$recipeID.'"
								data-recipeingredientid="'.$ingredient['pkRecipeIngredientID'].'" 
								data-componenttype="'.$ingredient['Type'].'" 
								data-quantity="'.formatQuantity($ingredient['fldQuantity']).'">';
								echo '<span data-originalquantity="'.$ingredient['fldQuantity'].'" class="Quantity">'.formatQuantity($ingredient['fldQuantity'])."</span>";
							
							if ($ingredient['fldUnit'] && $ingredient['pkUnitID']>1) {
								echo '<span class="Unit">';
								if ($ingredient['fldAbbreviation']) {
									echo $ingredient['fldAbbreviation'];
								} else {
									echo ' ' . ($ingredient['fldQuantity'] <= 1 ? $ingredient['fldUnit'] : $ingredient['fldPlural']);
								}
								echo '</span>';
							}
							echo ' ';
							if($ingredient['Type']==2){echo "<u><a class='RecipeReference' target='_blank' href='?id=".$ingredient['fkComponentID']."'>";}
							echo '<span class="IngredientName">';
							echo htmlspecialchars($ingredient['ComponentName']);
							echo '</span>';
							if($ingredient['Type']==2){echo '</a></u>';}
							
							if($ingredient['fldPrepNotes']) {
								echo '<span class="PrepNotes">';
								echo ', ' . htmlspecialchars($ingredient['fldPrepNotes']);
								echo '</span>';
							}
						echo '</p>';
					}
					if($ingredient['Type']=="3"){
						echo '<p class="subheader">'.$ingredient['ComponentName'].'</p>';
					}
				}//foreach($ingredients as $ingredient)
				?>
				  <button id="AddToList" <?php if(isset($ActiveList)){echo 'style="display:none"';}?> >Add to List</button>
				  <button id="RemoveFromList" <?php if(!isset($ActiveList)){echo 'style="display:none"';}?>>Remove From List</button>
				  <p id="AddedToList" <?php if(!isset($ActiveList)){echo 'style="display:none"';}?>>(Added to list)</p>
			</div>
	
			<div class="right-section">
				<div class="instructions">
					<h2>Instructions</h2>
					<?php 
					foreach($instructions as $index => $instruction): 
						echo '<div class="instruction-step">';
						echo '<p class="stepNumber">STEP ' . ($index + 1) . '</p>';
						echo '<p class="recipeStep">' . htmlspecialchars($instruction['fldInstructions']) . '</p>';
						echo '</div>';
					endforeach; 
					?>
				</div>
	
				<?php if(!empty($tips)): ?>
				<div class="tips">
					<h2>Tips</h2>
					<ul>
					<?php foreach($tips as $tip): ?>
						<li><?php echo htmlspecialchars($tip['fldRecipeTip']); ?></li>
					<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>
			
			</div>
<?php
if (!empty($recipe['fldNutrition'])) {
    $nutritionData = json_decode($recipe['fldNutrition'], true); // Decode JSON to associative array
    $totalWeight = $nutritionData['totalWeight'];



	if (!empty($recipe['fldYield']) && !empty($recipe['fldYieldUnit']) && $recipe['fldYield'] > 0) {
		$servings = $recipe['fldYield'];} 
		else {$servings = $totalWeight / 100;}
	
	if (!empty($recipe['fldYield'])) {$servingUnit = $recipe['fldYieldUnit'];} 
		else {$servingUnit = "100 grams";}

	if (strtolower($servingUnit) === 'servings') {$servingUnit = 'serving';}
	
	if (!empty($recipe['fldYield'])) {
		$servingDescription = "Per $servingUnit (" . round($totalWeight / $servings) . "g)";
	} else {
		$servingDescription = "Per 100 grams";
	}




    echo "<div style='font-family: Arial, sans-serif; max-width: 300px; border: 2px solid black; padding: 10px;'>";
    echo "<div style='font-size: 18px; font-weight: bold; padding-bottom: 1px;'>Nutrition Facts</div>"; // Further reduced padding-bottom
    echo "<div style='font-weight: bold; font-size: 14px; margin-top: 1px; margin-bottom: 5px;'>$servingDescription</div>"; // Further reduced margin-top and margin-bottom
    echo "<div style='border-bottom: 8px solid black; margin-bottom: 10px;'></div>";

    foreach ($nutritionData['nutrients'] as $nutrient) {
        $amountPerServing = $nutrient['totalAmount'] / $servings;
        $amountPerServing = $amountPerServing >= 1000 ? number_format($amountPerServing) : ($amountPerServing > 10 ? round($amountPerServing) : round($amountPerServing, 1));
        $nutrientName = htmlspecialchars($nutrient['nutrient'], ENT_QUOTES, 'UTF-8');
        $formattedValue = htmlspecialchars($amountPerServing . "" . $nutrient['unit'], ENT_QUOTES, 'UTF-8');

        // Stop printing nutrients after "Protein"
        if ($nutrientName === "Protein") {
            $border = "border-bottom: 8px solid black;";
            echo "<div style='display: flex; justify-content: space-between; margin-bottom: 5px; font-weight: bold; $border'>";
            echo "<div>$nutrientName</div><div>$formattedValue</div>";
            echo "</div>";
            break; // Stop processing further nutrients
        }

        // Rules for bold and indented nutrients
        $bold = in_array($nutrientName, ["Calories", "Total Fat", "Carbohydrates", "Protein", "Cholesterol", "Sodium"]) ? "font-weight: bold;" : "";
        $indent = in_array($nutrientName, ["Saturated", "Trans", "Monounsaturated", "Polyunsaturated", "Sugars", "Added Sugars", "Dietary Fiber", "Soluble", "Insoluble"]) ? "padding-left: 25px;" : "";
        $border = "border-bottom: 1px solid black;";

        echo "<div style='display: flex; justify-content: space-between; margin-bottom: 5px; $bold $indent $border'>";
        echo "<div>$nutrientName</div><div>$formattedValue</div>";
        echo "</div>";
    }

    echo "</div>";
} else {
    // Uncomment the following line for debugging purposes
    // echo "fldNutrition is empty or null.";
}
?>	
			
			
		   <?php if(!empty($tags)): ?>
			<div class="recipe-tags">
				<h2>Recipe Tags</h2>
				<?php foreach($tags as $tag): ?>
					<a href="/?tag=<?php echo htmlspecialchars($tag['pkTagNameID']); ?>" class="tag">
						<?php echo htmlspecialchars($tag['fldTagName']); ?>
					</a>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
			
		</div>
	
	   
	</div>
	
	
	<!-- Print version -->
	<div class="print-container">
		<h1><?php echo htmlspecialchars($recipe['fldRecipeName']); ?></h1>
		
		<div class="print-content">
			<div class="print-ingredients">	
				<h2>Ingredients</h2>
				
				<?php 
				foreach($ingredients as $ingredient){
					
					if(in_array($ingredient['Type'], [1, 2])){
						echo'<p class="ingredient">';
						echo '<span class="Quantity">'.formatQuantity($ingredient['fldQuantity'])."</span>";
					
						if ($ingredient['fldUnit'] && $ingredient['pkUnitID']>1) {
							if ($ingredient['fldAbbreviation']) {
								echo $ingredient['fldAbbreviation'];
							} else {
								echo ' ' . ($ingredient['fldQuantity'] <= 1 ? $ingredient['fldUnit'] : $ingredient['fldPlural']);
							}
						}
					
						echo ' ' . htmlspecialchars($ingredient['ComponentName']);
						
						if($ingredient['fldPrepNotes']) {
							echo ', ' . htmlspecialchars($ingredient['fldPrepNotes']);
						}//PrepNotes
						echo '</p>';
					}//If regular ingredient or recipe-as-ingredient
					
					//If subheader
					if($ingredient['Type']=="3"){
						echo '<p class="subheader">'.$ingredient['ComponentName'].'</p>';
					}//if subheader	
				}//foreach $ingredient	
				?>
			</div>
	
			<div class="print-instructions">
				<?php if (!empty($instructions)): ?>
					<h2>Instructions</h2>
					<?php 
					$step = 1;
					foreach($instructions as $instruction): ?>
						<p class="stepNumber">Step <?php echo $step++; ?></p>
						<p class="recipeStep"><?php echo htmlspecialchars($instruction['fldInstructions']); ?></p>
					<?php endforeach; ?>
				<?php endif; ?>
	
				<?php if (!empty($tips)): ?>
					<h2>Tips</h2>
					<ul>
					<?php foreach($tips as $tip): ?>
						<li><?php echo htmlspecialchars($tip['fldRecipeTip']); ?></li>
					<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>
	
	<script> var RecipeID='<?php echo $recipeID;?>'; </script>
	<script src="js/ShoppingList.js"></script>


</body>

</html>
