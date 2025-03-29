# Compares food_portion and food_nutrient and removes any records from food_portion 
# that do not have a corresponding fdc_id in food_nutrient, and outputs a new csv file
import csv

def load_fdc_ids(file_path):
    fdc_ids = set()
    with open(file_path, mode='r', newline='', encoding='utf-8') as file:
        reader = csv.DictReader(file)
        for row in reader:
            fdc_ids.add(row['fdc_id'])
    return fdc_ids

def load_food_descriptions(file_path):
    food_descriptions = {}
    with open(file_path, mode='r', newline='', encoding='utf-8') as file:
        reader = csv.DictReader(file)
        for row in reader:
            food_descriptions[row['fdc_id']] = row['description']
    return food_descriptions

def find_missing_fdc_ids(food_portion_path, food_nutrient_path):
    print(f"Loading fdc_ids from {food_portion_path} ...")
    food_portion_ids = load_fdc_ids(food_portion_path)
    print(f"Loaded {len(food_portion_ids)} unique fdc_ids from {food_portion_path}")

    print(f"Loading fdc_ids from {food_nutrient_path} ...")
    food_nutrient_ids = load_fdc_ids(food_nutrient_path)
    print(f"Loaded {len(food_nutrient_ids)} unique fdc_ids from {food_nutrient_path}")

    missing_fdc_ids = food_portion_ids - food_nutrient_ids
    return missing_fdc_ids, food_portion_ids

def main():
    food_portion_path = '/Users/michaelfemia/Sites/localhost/nutrientinfo/nutrientimport/food_portion.csv'
    food_nutrient_path = '/Users/michaelfemia/Sites/localhost/nutrientinfo/nutrientimport/food_nutrient.csv'
    food_path = '/Users/michaelfemia/Sites/localhost/nutrientinfo/nutrientimport/food.csv'
    output_missing_path = '/Users/michaelfemia/Sites/localhost/nutrientinfo/nutrientimport/missing_fdc_ids_with_description.csv'
    output_filtered_path = '/Users/michaelfemia/Sites/localhost/nutrientinfo/nutrientimport/filtered_food_portion.csv'

    missing_fdc_ids, food_portion_ids = find_missing_fdc_ids(food_portion_path, food_nutrient_path)
    food_descriptions = load_food_descriptions(food_path)

    # Output missing fdc_ids with descriptions
    with open(output_missing_path, mode='w', newline='', encoding='utf-8') as file:
        writer = csv.writer(file)
        writer.writerow(['fdc_id', 'description'])
        for fdc_id in missing_fdc_ids:
            description = food_descriptions.get(fdc_id, 'No description available')
            writer.writerow([fdc_id, description])

    print(f"Missing fdc_ids with descriptions have been written to {output_missing_path}")

    # Output filtered food_portion.csv
    with open(food_portion_path, mode='r', newline='', encoding='utf-8') as infile, \
         open(output_filtered_path, mode='w', newline='', encoding='utf-8') as outfile:
        reader = csv.DictReader(infile)
        writer = csv.DictWriter(outfile, fieldnames=reader.fieldnames)
        writer.writeheader()
        for row in reader:
            if row['fdc_id'] not in missing_fdc_ids:
                writer.writerow(row)

    print(f"Filtered food_portion.csv has been written to {output_filtered_path}")

if __name__ == "__main__":
    main()