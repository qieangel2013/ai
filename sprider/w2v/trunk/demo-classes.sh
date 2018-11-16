make
#if [ ! -e text8 ]; then
#  wget http://mattmahoney.net/dc/text8.zip -O text8.gz
#  gzip -d text8.gz -f
#fi
time ./word2vec -train /data/w2v/data/corpus_seg.txt  -output wiki_classes.txt -cbow 1 -size 200 -window 8 -negative 25 -hs 0 -sample 1e-4 -threads 20 -iter 15 -classes 500
sort wiki_classes.txt -k 2 -n > wiki_classes.sorted.txt
echo The word classes were saved to file wiki_classes.sorted.txt
