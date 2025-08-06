# extract_chars.py
with open("workspace/tts-dataset/metadata.csv", encoding="utf-8") as f:
    lines = f.readlines()

chars = sorted(set("".join([line.strip().split("|")[-1] for line in lines])))
print("".join(chars))
