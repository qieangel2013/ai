#-*- coding: utf8 -*-
import gensim
import sys

readmodel = gensim.models.KeyedVectors.load_word2vec_format("/data/w2v/trunk/vectors.bin", binary = False)

filePath='userdict.txt'
fileTrainRead = []

with open(filePath) as fileTrainRaw:
    for line in fileTrainRaw:
        #fileTrainRead.append(line)
        try:
            #print line.replace('\n','')
            result = readmodel.most_similar(unicode(line,'utf-8').replace('\n',''),topn=30) #输出30个最相关的词
            for e in result:
                print e[0], e[1]
        except Exception, e:
            print e
            continue

