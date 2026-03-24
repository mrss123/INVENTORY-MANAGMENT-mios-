# # first lets create function that do binary search 

# def search_spare(partnumber, inventory):
#     low = 0
#     high = len(inventory) - 1 
#     while low <= high:
#         medium = (low + high)//2
#         # now lets unpack the values of the tubles 
#         part_number, description, qauntity = inventory[medium]
#         if partnumber == part_number:
#             return inventory[medium]
#         elif partnumber < part_number:
#             high = medium - 1
#         else:
#             low = medium + 1
#     return -1 

# # lets create function that returns description and quantity of the partnumber searche
# def get_detail(partnumber, inventory):
#     result = search_spare(partnumber, inventory)
#     if result != -1:
#         print(f"\n")
#         print(f"-"*100)
#         print("\n")
#         print(f"Description: {result[1]}\n")
#         print(f"Available: {result[2]}")
#         # print("\n")
#         # print("-"*100)
#     else:
#         print("\n")
#         print("-"*100)
#         print("\n")
#         print(f"{partnumber} Is not Available")
#         # print("\n")
#         # print("-"*100)

# Spare_Part = [("5802055310", "Thermostat", 1000),
#               ("2992261", "Air_Dryer", 1000),
#               ("000 090 02 12", "Manuel Feed Fuel Filter", 200),
#               ("000 260 49 98", "Gear Lever Actuator Full Repair Kit", 300),
#               ("001 260 25 63", "Shift Cylinder", 300),
#               ("001 295 56 07", "Clutch Servo", 200),
#               ("002 260 62 57", "Gear Box Valve", 300),
#               ("0120468131", "Alternator", 600),
#               ("1102864", "Wheel bearing", 400),
#               ("1441237", "Gasket Kit, Unit Injector", 677),
#               ("17694165", "Oil Filter Housing", 788),
#               ("1905273", "Wheel Hub Bearing", 800),
#               ("4882440", "Friction Disk", 700)]
# Spare_Part.sort(key=lambda x: x[0])

# while True:
#     print("\n")
#     print("-"*100)
#     print("\n")
#     partnumber = input("Enter Part-Number to be searched(q to quit): ")
#     print("\n")
#     print("-"*100)
#     if partnumber.upper() == "Q":
#         print("Thank you using this site")
#         break 
#     get_detail(partnumber, Spare_Part)




class Sparepart:
    def init(self, Description, PartNumber, UnitPrice, Quantity):
        self.Description = Description
        self.PartNumber = PartNumber
        self.UnitPrice = UnitPrice
        self.Quantity = Quantity

    def str(self):
        return (
            "-"*50 + "\n" +
            f"Description: {self.Description}\n"
            f"Part-Number: {self.PartNumber}\n"
            f"Unit-Price: {self.UnitPrice}\n"
            f"Quantity: {self.Quantity}\n" +
            "-"*50
        )

    def check(self, partnumber):
        if partnumber == self.PartNumber:
            return str(self)
        else:
            return None


sp1 = Sparepart("Thermostat", "5802055310", "45000", "90")
sp2 = Sparepart("Piston-ring", "2992261", "3400", "100")
sp3 = Sparepart("Oil_seal", "401013", "4500", "89")
sp4 = Sparepart("Shaft_seal", "40101540", "6700", "100")

parts = [sp1, sp2, sp3, sp4]


while True:
    print("-"*100)
    print("\n")
    partnumber = input("Enter partnumber (q to quit): ")
    print("\n")
    print("-"*100)
    if partnumber == "q":
        print("\n")
        print("Thank you visiting our site!!")
        break

    found = False
    for p in parts:
        result = p.check(partnumber)
        if result:
            print(result)
            found = True
            break

    if not found:
        print("Not Available")

print("\n")
print("Thank you visiting our site!!")